<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2005 Ingo Renner (typo3@ingo-renner.com)
*  All rights reserved
*
*  This script is part of the Typo3 project. The Typo3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/
/**
 * class.tx_timtabthemek2_fe.php
 *
 * Class to localize strings and do custom theme stuff
 *
 * $Id$
 *
 * @author Ingo Renner <typo3@ingo-renner.com>
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 */

#$PATH_timtab = t3lib_extMgm::extPath('timtab');
require_once(PATH_tslib.'class.tslib_pibase.php');

class tx_timtabthemek2_ajax extends tslib_pibase {
	var $cObj; // The backReference to the mother cObj object set at call time
	// Default plugin variables:
	var $prefixId 		= 'tx_timtabthemek2_ajax';		// Same as class name
	var $scriptRelPath 	= 'class.tx_timtabthemek2_ajax.php';	// Path to this script relative to the extension dir.
	var $extKey 		= 'timtab_theme_k2';	// The extension key.

	var $conf;
	var $tt_news;
	var $postData;
	var $failed = false;

	/**
	 * main function which executes all steps
	 *
	 * @param	array		an array of markers coming from tt_news
	 * @param	array		the configuration coming from tt_news
	 * @return	array		modified marker array
	 */
	function main() {
		$this->init();
		$content = '';
		
		if(empty($this->tt_news)) {
			$this->fail(
				'The post you are trying to comment on does not curently exist in the database.'
			);
		} elseif(!$this->tt_news['tx_timtab_comments_allowed'] && !$this->failed) {
			$this->fail('Sorry, comments are closed for this post.');
		}
		
		$this->checkInput();
		
		//effects won't work when return-ing instead of echo-ing and exit-ing
		//as acomment with the parse time might be printed and thus would be the <ol>.lastChild
		echo $this->insertComment();
		exit;
	}
	
	/**
	 * initializes the configuration for the extension
	 *
	 * @param	array		an array of markers coming from tt_news
	 * @param	array		the configuration coming from tt_news
	 * @return	void
	 */
	function init() {
		$this->cObj = t3lib_div::makeInstance('tslib_cObj'); // local cObj.
		$this->pi_loadLL(); // Loading language-labels
		
		$this->conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_timtab.'];
		
		if(t3lib_div::_POST()) {
			$this->postData = $this->getPostData();		
			$this->tt_news  = $this->getCurrentPost($this->postData['tt_news_id']);
		} else {
			$this->fail('No Data.');	
		}
	}
	
	function fail($message) {
		$this->failed = true;
		header('HTTP/1.0 500 Internal Server Error');
		
		echo $message;
		exit;
	}
	
	function checkInput() {
		$author       = trim($this->postData['author']);
		$author_email = trim($this->postData['email']);
		$comment      = trim($this->postData['comment']);
		
		if(!t3lib_div::validEmail($author_email) || empty($author)) {
			$this->fail($this->pi_getLL('fill_required'));
		} elseif(empty($comment)) {
			$this->fail($this->pi_getLL('fill_comment'));
		}
	}
	
	function insertComment() {	
		$slu = $GLOBALS['TSFE']->config['config']['sys_language_uid'];
		$slu = $slu ? $slu : '0';
		$pid = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_timtab_pi2.']['pidStoreComments'];
		$pid = $pid ? $pid : $GLOBALS['TSFE']->id;
		$time = time();
		$remote = t3lib_div::getIndpEnv('REMOTE_ADDR');
		
		$insertFields = array(
			'pid' => $pid,
			'uid_tt_news' => $this->postData['tt_news_id'],
			'sys_language_uid' => $slu,
			'tstamp' => $time,
			'crdate' => $time,
			'remote_addr' => $remote,
			'firstname' => trim($this->postData['author']),
			'email' => trim($this->postData['email']),
			'homepage' => trim($this->postData['url']),
			'entry' => trim($this->postData['comment'])			
		);
		
		$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery(
			'tx_veguestbook_entries',
			$insertFields
		);
		
		if(!$res) {
			$this->fail('DB error');
		}
		
		if(!$commentId = $GLOBALS['TYPO3_DB']->sql_insert_id()) {
			$this->fail();
		}
		
		$comment = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tx_veguestbook_entries',
			'uid = '.$commentId
		);
		
		$this->rememberUser();
		
		return $this->fillComment($comment[0]);		
	}
	
	function fillComment($commentData) {
		$this->conf['data'] = $commentData;
		$markerArray = array();
		
		$tmplPath = t3lib_extMgm::siteRelPath($this->extKey).'res/ve_guestbook.tmpl';		
		$template = $this->cObj->fileResource($tmplPath);
		
		$subpart  = $this->cObj->getSubPart($template, '###TEMPLATE_LIST###');
		$content  = $this->cObj->getSubPart($subpart, '###CONTENT###');
		$entry    = $this->cObj->getSubPart($content, '###ENTRY###');
				
		$timtab   = t3lib_div::makeInstance('tx_timtab_fe');
		$timtab->conf = $this->conf;
		$gravatar = $timtab->getGravatar();
		
		if(!empty($this->conf['data']['homepage'])) {
			$markerArray['###BLOG_COMMENTER_NAME###'] = '<a href="'.$this->conf['data']['homepage'].'" rel="external">'.$this->conf['data']['firstname'].'</a>';
		} else {
			$markerArray['###BLOG_COMMENTER_NAME###'] = $this->conf['data']['firstname'];
		}
		
		$markerArray['###BLOG_COMMENT_GRAVATAR###'] = $gravatar;
		$markerArray['###BLOG_COMMENT_NUM###'] = $commentData['uid'];
		$markerArray['###K2_COMMENT_PERMALINK###'] = $this->pi_getLL('comment_permalink');
		$markerArray['###GUESTBOOK_DATE###'] = strftime('%B %d, %Y, %H:%M', time());
		$markerArray['###GUESTBOOK_ENTRY###'] = $commentData['entry'];
				
		$commentOut = $this->cObj->substituteMarkerArray($entry, $markerArray);
		
		$matches = array();
		preg_match('#<li(.*?)>(.*)</li>#ims', $commentOut, $matches);
		
		return '<li style="display: none;"'.$matches[1].'>'.$matches[2].'</li>';
	}
	
	/**
	 * gets the current post when called by ve_guestbook
	 *
	 * @return	array
	 */
	function getCurrentPost() {
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tt_news',
			'uid = '.$this->postData['tt_news_id'].$this->cObj->enableFields('tt_news')
		);

		return $res[0];
	}
	
	function getPostData() {
		$postData = t3lib_div::_POST();
		
		return $postData;
	}
	
	/**
	 * connects to the hook in ve_guestbook to post process a comment entry
	 *
	 * @param	object		parentObject the calling ve_guestbook object
	 * @return	void
	 */
	function rememberUser() {
		//save user data for comment form so he doesn't have to type it in every time
		//only if user wants this and we are allowed to set cookies
		$postData = t3lib_div::_POST('tx_timtab');
		$remember = $postData['remember_visitor'];

		if (!$GLOBALS['TYPO3_CONF_VARS']['FE']['dontSetCookie'] && $remember) {
			$userInfo = implode('|',
				array(
					trim($this->postData['author']),
					trim($this->postData['email']),
					trim($this->postData['url']),
				)
			);

			setcookie('comment_info', $userInfo, time() + 3600 * 24 * 90, '/');
		}

	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/timtab_theme_k2/class.tx_timtabthemek2_ajax.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/timtab_theme_k2/class.tx_timtabthemek2_ajax.php']);
}

?>