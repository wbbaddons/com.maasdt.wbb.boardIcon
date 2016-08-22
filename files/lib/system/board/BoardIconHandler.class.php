<?php
namespace wbb\system\board;
use wbb\data\board\icon\BoardIcon;
use wbb\data\board\icon\BoardIconList;
use wbb\data\board\BoardList;
use wcf\data\application\Application;
use wcf\data\option\Option;
use wcf\system\event\EventHandler;
use wcf\system\io\File;
use wcf\system\style\StyleHandler;
use wcf\system\SingletonFactory;

/**
 * Handles the board icons file.
 * 
 * @author	Matthias Schmidt
 * @copyright	2014-2016 Maasdt
 * @license	Creative Commons Attribution-NonCommercial-ShareAlike <http://creativecommons.org/licenses/by-nc-sa/4.0/legalcode>
 * @package	com.maasdt.wbb.boardIcon
 * @subpackage	system.board
 * @category	Burning Board
 */
class BoardIconHandler extends SingletonFactory {
	/**
	 * list with available board icons
	 * @var	BoardIcon[]
	 */
	protected $boardIcons = null;
	
	/**
	 * Returns the board icon with the given id or null if no such board icon
	 * exists.
	 * 
	 * @param	integer		$iconID
	 * @return	BoardIcon
	 */
	protected function getBoardIcon($iconID) {
		if ($this->boardIcons === null) {
			$boardIconList = new BoardIconList();
			$boardIconList->readObjects();
			$this->boardIcons = $boardIconList->getObjects();
		}
		
		if (isset($this->boardIcons[$iconID])) {
			return $this->boardIcons[$iconID];
		}
		
		return null;
	}
	
	/**
	 * Returns the LESS code for the icon with the given name.
	 * 
	 * @param	string		$selector
	 * @param	string		$iconName
	 * @param	string		$iconColor
	 * @return	string
	 */
	protected function getIconLESSCode($selector, $iconName, $iconColor = null) {
		if (preg_match('~wbbBoardIcon(\d+)~', $iconName, $matches)) {
			$boardIcon = $this->getBoardIcon($matches[1]);
			if ($boardIcon) {
				return "\t&.".$selector." {\n\t\t&::before {\n\t\t\tcontent: '';\n\t\t}\n\t\t\n\t\tbackground-image: url(".$boardIcon->getLink().");\n\t\tbackground-size: 100%;\n\t\tbackground-repeat: no-repeat;\n\t}\n";
			}
			
			return '';
		}
		
		return "\t&.".$selector." {\n\t\tbackground-image: none;\n\t\t\n\t\t&::before {".($iconName ? "\n\t\t\tcontent: @".$iconName.";" : "").($iconColor ? "\n\t\t\tcolor: ".$iconColor.";" : "")."\n\t\t}\n\t}\n";
	}
	
	/**
	 * Writes the style file with the board icons.
	 */
	public function writeStyleFile() {
		$boardList = new BoardList();
		$boardList->readObjects();
		
		$fileContent = '';
		
		$options = Option::getOptions();
		if ($options['WBB_DEFAULT_BOARD_ICON']->optionValue || $options['WBB_DEFAULT_NEW_BOARD_ICON']->optionValue || $options['WBB_DEFAULT_EXTERNAL_LINK_ICON']->optionValue) {
			$fileContent .= ".wbbBoardList li > .wbbBoard > .icon,\n.wbbSubBoards li > .icon {\n";
			
			if ($options['WBB_DEFAULT_BOARD_ICON']->optionValue) {
				$fileContent .= $this->getIconLESSCode('icon-folder-close-alt', $options['WBB_DEFAULT_BOARD_ICON']->optionValue);
			}
			if ($options['WBB_DEFAULT_NEW_BOARD_ICON']->optionValue) {
				$fileContent .= $this->getIconLESSCode('icon-folder-close', $options['WBB_DEFAULT_NEW_BOARD_ICON']->optionValue);
			}
			if ($options['WBB_DEFAULT_EXTERNAL_LINK_ICON']->optionValue) {
				$fileContent .= $this->getIconLESSCode('icon-globe', $options['WBB_DEFAULT_EXTERNAL_LINK_ICON']->optionValue);
			}
			
			$fileContent .= "}\n\n";
		}
		
		if ($options['WBB_DEFAULT_ARCHIVE_ICON']->optionValue) {
			$fileContent .= ".wbbBoardList li > .wbbBoard:not(.new) > .icon,\n.wbbSubBoards li:not(.new) > .icon {\n";
			$fileContent .= $this->getIconLESSCode('icon-lock', $options['WBB_DEFAULT_ARCHIVE_ICON']->optionValue);
			$fileContent .= "}\n\n";
		}
		if ($options['WBB_DEFAULT_NEW_ARCHIVE_ICON']->optionValue) {
			$fileContent .= ".wbbBoardList li > .wbbBoard:not(.new) > .icon,\n.wbbSubBoards li:not(.new) > .icon {\n";
			$fileContent .= $this->getIconLESSCode('icon-lock', $options['WBB_DEFAULT_NEW_ARCHIVE_ICON']->optionValue);
			$fileContent .= "}\n\n";
		}
		
		foreach ($boardList as $board) {
			if (($board->isBoard() || $board->isExternalLink()) && ($board->icon || $board->iconColor || $board->iconNew || $board->iconNewColor)) {
				$fileContent .= ".wbbBoardList li[data-board-id=\"".$board->boardID."\"] > .wbbBoard > .icon,\n.wbbSubBoards li[data-board-id=\"".$board->boardID."\"] > .icon {\n";
				
				if ($board->isBoard()) {
					if ($board->isClosed) {
						$fileContent .= $this->getIconLESSCode('icon-lock', $board->icon, $board->iconColor ?: null);
					}
					else {
						if ($board->icon || $board->iconColor) {
							$fileContent .= $this->getIconLESSCode('icon-folder-close-alt', $board->icon, $board->iconColor ?: null);
						}
						
						if ($board->iconNew || $board->iconNewColor) {
							$fileContent .= $this->getIconLESSCode('icon-folder-close', $board->iconNew, $board->iconNewColor ?: null);
						}
					}
				}
				else if ($board->isExternalLink()) {
					if ($board->icon || $board->iconColor) {
						$fileContent .= $this->getIconLESSCode('icon-globe', $board->icon, $board->iconColor ?: null);
					}
				}
				
				$fileContent .= "}\n";
				
				if ($board->isBoard() && $board->isClosed && ($board->iconNew || $board->iconNewColor)) {
					$fileContent .= ".wbbBoardList li[data-board-id=\"".$board->boardID."\"] > .wbbBoard.new > .icon,\n.wbbSubBoards li[data-board-id=\"".$board->boardID."\"].new > .icon {\n";
					
					$fileContent .= $this->getIconLESSCode('icon-lock', $board->iconNew, $board->iconNewColor ?: null);
					
					$fileContent .= "}\n";
				}
			}
		}
		
		// write file
		$styleFile = new File(Application::getDirectory('wbb').'style/boardIcon.less');
		$styleFile->write("// Do not edit this file manually! Manual changes will be overwriten.\n\n");
		
		if ($fileContent) {
			$styleFile->write($fileContent);
		}
		
		$styleFile->close();
		
		EventHandler::getInstance()->fireAction($this, 'writeStyleFile');
		
		// reset stylesheets to apply changes
		StyleHandler::resetStylesheets();
	}
}
