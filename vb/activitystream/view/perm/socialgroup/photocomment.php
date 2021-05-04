<?php

/* ======================================================================*\
  || #################################################################### ||
  || # vBulletin 4.2.0 
  || # ---------------------------------------------------------------- # ||
  || # Copyright ©2000-2012 vBulletin Solutions Inc. All Rights Reserved. ||
  || # This file may not be redistributed in whole or significant part. # ||
  || # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
  || # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
  || #################################################################### ||
  \*====================================================================== */

class vB_ActivityStream_View_Perm_Socialgroup_Photocomment extends vB_ActivityStream_View_Perm_Socialgroup_Base
{
	public function __construct(&$content)
	{
		$this->requireExist['vB_ActivityStream_View_Perm_Album_Photo'] = 1;
		$this->requireExist['vB_ActivityStream_View_Perm_Socialgroup_Photo'] = 1;
		return parent::__construct($content);
	}

	public function group($activity)
	{
		if (!$this->fetchCanUseGroups() OR !vB::$vbulletin->options['pc_enabled'])
		{
			return;
		}

		if (!$this->content['socialgroup_picturecomment'][$activity['contentid']])
		{
			$this->content['picturecommentid'][$activity['contentid']] = 1;
		}
	}

	public function process()
	{
		return $this->processPicturecommentids();
	}

	public function fetchCanView($record)
	{
		$this->processUsers();
		return $this->fetchCanViewSocialgroupPhotoComment($record['contentid']);
	}

	/*
	 * Register Template
	 *
	 * @param	string	Template Name
	 * @param	array	Activity Record
	 *
	 * @return	string	Template
	 */
	public function fetchTemplate($templatename, $activity)
	{
		$commentinfo =& $this->content['socialgroup_picturecomment'][$activity['contentid']];
		$groupinfo =& $this->content['socialgroup'][$commentinfo['groupid']];

		$activity['postdate'] = vbdate(vB::$vbulletin->options['dateformat'], $activity['dateline'], true);
		$activity['posttime'] = vbdate(vB::$vbulletin->options['timeformat'], $activity['dateline']);

		$preview = strip_quotes($commentinfo['pagetext']);
		$commentinfo['preview'] = htmlspecialchars_uni(fetch_censored_text(
			fetch_trimmed_title(strip_bbcode($preview, false, true, true, true),
				vb::$vbulletin->options['as_snippet'])
		));
		$userinfo = $this->fetchUser($activity['userid'], $commentinfo['postusername']);

		$templater = vB_Template::create($templatename);
			$templater->register('userinfo', $userinfo);
			$templater->register('activity', $activity);
			$templater->register('commentinfo', $commentinfo);
			$templater->register('groupinfo', $groupinfo);
		return $templater->render();
	}
}

/*======================================================================*\
|| ####################################################################
|| # 
|| # CVS: $RCSfile$ - $Revision: 57655 $
|| ####################################################################
\*======================================================================*/