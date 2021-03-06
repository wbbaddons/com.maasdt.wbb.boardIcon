<?php
namespace wbb\data\board\icon;
use wbb\system\board\BoardIconHandler;
use wcf\data\AbstractDatabaseObjectAction;
use wcf\system\exception\UserInputException;
use wcf\system\upload\DefaultUploadFileValidationStrategy;
use wcf\system\upload\UploadFile;
use wcf\system\upload\UploadHandler;
use wcf\system\WCF;

/**
 * Executes board-icon related actions.
 * 
 * @author	Matthias Schmidt
 * @copyright	2014-2016 Maasdt
 * @license	Creative Commons Attribution-NonCommercial-ShareAlike <http://creativecommons.org/licenses/by-nc-sa/4.0/legalcode>
 * @package	com.maasdt.wbb.boardIcon
 * @subpackage	data.board.icon
 * @category	Burning Board
 * 
 * @method	BoardIconEditor[]	getObjects()
 * @method	BoardIconEditor		getSingleObject()
 */
class BoardIconAction extends AbstractDatabaseObjectAction {
	/**
	 * board icon the uploaded icon file belongs to
	 * @var	BoardIcon
	 */
	protected $boardIcon;
	
	/**
	 * @inheritDoc
	 */
	protected $permissionsDelete = ['admin.board.canManageBoardIcon'];
	
	/**
	 * @inheritDoc
	 */
	protected $requireACP = ['delete', 'update', 'upload'];
	
	/**
	 * @inheritDoc
	 * @return	BoardIcon
	 */
	public function create() {
		$this->parameters['data']['fileExtension'] = WCF::getSession()->getVar('wbbBoardIcon-'.$this->parameters['tmpHash']);
		$fileLocation = WBB_DIR.'icon/board/tmp/'.$this->parameters['tmpHash'].'.'.$this->parameters['data']['fileExtension'];
		
		$this->parameters['data']['fileHash'] = sha1_file($fileLocation);
		$this->parameters['data']['filesize'] = filesize($fileLocation);
		
		/** @var BoardIcon $boardIcon */
		$boardIcon = parent::create();
		
		// move file to final position
		rename($fileLocation, $boardIcon->getLocation());
		WCF::getSession()->unregister('wbbBoardIcon-'.$this->parameters['tmpHash']);
		
		return $boardIcon;
	}
	
	/**
	 * @inheritDoc
	 */
	public function delete() {
		$returnValue = parent::delete();
		
		// delete files
		foreach ($this->getObjects() as $boardIcon) {
			@unlink($boardIcon->getLocation());
		}
		
		BoardIconHandler::getInstance()->writeStyleFile();
		
		return $returnValue;
	}
	
	/**
	 * Validates the 'upload' action.
	 */
	public function validateUpload() {
		// validate permissions
		WCF::getSession()->checkPermissions($this->permissionsDelete);
		
		$this->readInteger('boardIconID', true);
		$this->readString('tmpHash');
		
		if ($this->parameters['boardIconID']) {
			$this->boardIcon = new BoardIcon($this->parameters['boardIconID']);
			if (!$this->boardIcon->iconID) {
				throw new UserInputException('boardIconID');
			}
		}
		
		/** @var UploadHandler $uploadHandler */
		$uploadHandler = $this->parameters['__files'];
		
		if (count($uploadHandler->getFiles()) != 1) {
			throw new UserInputException('files');
		}
		
		// validate file
		$uploadHandler->validateFiles(new DefaultUploadFileValidationStrategy(PHP_INT_MAX, ['gif', 'jpg', 'jpeg', 'png']));
	}
	
	/**
	 * Uploads a board icon.
	 * 
	 * @return	string[]
	 */
	public function upload() {
		/** @var UploadFile $file */
		/** @noinspection PhpUndefinedMethodInspection */
		$file = $this->parameters['__files']->getFiles()[0];
		
		$errorType = $file->getValidationErrorType();
		if (!$errorType) {
			$imageData = $file->getImageData();
			if ($imageData === null) {
				$errorType = 'noImage';
			}
			else if ($imageData['height'] < 32) {
				$errorType = 'minHeight';
			}
			else if ($imageData['width'] < 32) {
				$errorType = 'minWidth';
			}
			else if ($this->boardIcon) {
				$fileHash = sha1_file($file->getLocation());
				$newFileLocation = WBB_DIR.'icon/board/'.$this->boardIcon->iconID.'-'.$fileHash.'.'.$file->getFileExtension();
				if (@copy($file->getLocation(), $newFileLocation)) {
					@unlink($file->getLocation());
					
					$boardIconEditor = new BoardIconEditor($this->boardIcon);
					$boardIconEditor->update([
						'fileExtension' => $file->getFileExtension(),
						'fileHash' => $fileHash,
						'filesize' => filesize($newFileLocation)
					]);
					
					BoardIconHandler::getInstance()->writeStyleFile();
					
					$this->boardIcon = new BoardIcon($this->boardIcon->iconID);
					
					return [
						'url' => $this->boardIcon->getLink()
					];
				}
				else {
					$errorType = 'uploadFailed';
				}
			}
			else if (@copy($file->getLocation(), WBB_DIR.'icon/board/tmp/'.$this->parameters['tmpHash'].'.'.$file->getFileExtension())) {
				@unlink($file->getLocation());
				
				WCF::getSession()->register('wbbBoardIcon-'.$this->parameters['tmpHash'], $file->getFileExtension());
				
				return [
					'url' => WCF::getPath('wbb').'icon/board/tmp/'.$this->parameters['tmpHash'].'.'.$file->getFileExtension()
				];
			}
			else {
				$errorType = 'uploadFailed';
			}
		}
		
		return [
			'errorMessage' => WCF::getLanguage()->get('wbb.acp.boardIcon.icon.error.'.$errorType)
		];
	}
}
