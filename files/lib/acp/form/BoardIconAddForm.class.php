<?php
namespace wbb\acp\form;
use wbb\data\board\icon\BoardIconAction;
use wbb\data\board\icon\BoardIconEditor;
use wcf\data\package\PackageCache;
use wcf\form\AbstractForm;
use wcf\system\exception\UserInputException;
use wcf\system\language\I18nHandler;
use wcf\system\WCF;
use wcf\util\StringUtil;

/**
 * Shows the form to add a new board icon.
 * 
 * @author	Matthias Schmidt
 * @copyright	2014 Maasdt
 * @license	Creative Commons Attribution-NonCommercial-ShareAlike <http://creativecommons.org/licenses/by-nc-sa/4.0/legalcode>
 * @package	com.maasdt.wbb.boardIcon
 * @subpackage	acp.form
 * @category	Burning Board
 */
class BoardIconAddForm extends AbstractForm {
	/**
	 * @see	\wcf\page\AbstractPage::$activeMenuItem
	 */
	public $activeMenuItem = 'wbb.acp.menu.link.boardIcon.add';
	
	/**
	 * @see	\wcf\page\AbstractPage::$neededPermission
	 */
	public $neededPermission = array('admin.board.canManageBoardIcon');
	
	/**
	 * temporary hash used to identify uploaded icons
	 * @var	string
	 */
	public $tmpHash = '';
	
	/**
	 * @see	\wcf\page\IPage::assignVariables()
	 */
	public function assignVariables() {
		parent::assignVariables();
		
		I18nHandler::getInstance()->assignVariables();
		
		WCF::getTPL()->assign(array(
			'action' => 'add',
			'tmpHash' => $this->tmpHash
		));
	}
	
	/**
	 * @see	\wcf\form\IForm::readFormParameters()
	 */
	public function readFormParameters() {
		parent::readFormParameters();
		
		I18nHandler::getInstance()->readValues();
	}
	
	/**
	 * @see	\wcf\page\IPage::readParameters()
	 */
	public function readParameters() {
		parent::readParameters();
		
		I18nHandler::getInstance()->register('title');
		
		if (isset($_REQUEST['tmpHash'])) {
			$this->tmpHash = StringUtil::trim($_REQUEST['tmpHash']);
		}
		if (empty($this->tmpHash)) {
			$this->tmpHash = StringUtil::getRandomID();
		}
	}
	
	/**
	 * @see	\wcf\form\IForm::save()
	 */
	public function save() {
		parent::save();
		
		$this->objectAction = new BoardIconAction(array(), 'create', array(
			'data' => array_merge($this->additionalFields, array(
				'title' => I18nHandler::getInstance()->isPlainValue('title') ? I18nHandler::getInstance()->getValue('title') : ''
			)),
			'tmpHash' => $this->tmpHash
		));
		$returnValues = $this->objectAction->executeAction();
		
		if (!I18nHandler::getInstance()->isPlainValue('title')) {
			$boardIconEditor = new BoardIconEditor($returnValues['returnValues']);
			$boardIconEditor->update(array(
				'title' => 'wbb.acp.boardIcon.title.boardIcon'.$boardIconEditor->iconID
			));
			
			I18nHandler::getInstance()->save('title', 'wbb.acp.boardIcon.title'.$boardIconEditor->iconID, 'wbb.acp.boardIcon', PackageCache::getInstance()->getPackageID('com.maasdt.wbb.boardIcon'));
		}
		
		$this->saved();
	}
	
	/**
	 * @see	\wcf\form\AbstractForm::saved()
	 */
	protected function saved() {
		parent::saved();
		
		I18nHandler::getInstance()->reset();
		
		// show success message
		WCF::getTPL()->assign('success', true);
	}
	
	/**
	 * @see	\wcf\form\IForm::validate()
	 */
	public function validate() {
		parent::validate();
		
		if (!I18nHandler::getInstance()->validateValue('title')) {
			if (I18nHandler::getInstance()->isPlainValue('title')) {
				throw new UserInputException('title');
			}
			else {
				throw new UserInputException('title', 'multilingual');
			}
		}
		
		$this->validateIcon();
	}
	
	/**
	 * Checks if an icon has been uploaded.
	 */
	protected function validateIcon() {
		if (!WCF::getSession()->getVar('wbbBoardIcon-'.$this->tmpHash)) {
			throw new UserInputException('icon');
		}
	}
}
