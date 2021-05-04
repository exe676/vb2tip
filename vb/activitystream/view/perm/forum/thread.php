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

class vB_ActivityStream_View_Perm_Forum_Thread extends vB_ActivityStream_View_Perm_Forum_Base
{
	public function __construct(&$content)
	{
		$this->requireFirst['vB_ActivityStream_View_Perm_Forum_Post'] = 1;
		return parent::__construct($content);
	}

	public function group($activity)
	{
		if (!$this->content['thread'][$activity['contentid']])
		{
			$this->content['threadid'][$activity['contentid']] = 1;
		}
	}

	public function process()
	{
		if (!$this->content['threadid'])
		{
			return true;
		}

		$threads = vB::$db->query_read_slave("
			SELECT
				t.threadid, t.title, t.forumid, t.pollid, t.open, t.views, t.visible, t.postuserid, t.postuserid AS userid, t.replycount,
				t.postusername, fp.pagetext
			FROM " . TABLE_PREFIX . "thread AS t
			INNER JOIN " . TABLE_PREFIX . "post AS fp ON (t.firstpostid = fp.postid)
			WHERE
				t.threadid IN (" . implode(",", array_keys($this->content['threadid'])) . ")
					AND
				t.visible <> 2
		");
		while ($thread = vB::$db->fetch_array($threads))
		{
			$this->content['forumid'][$thread['forumid']] = 1;
			$this->content['thread'][$thread['threadid']] = $thread;
			$this->content['userid'][$thread['postuserid']] = 1;
		}

		$this->content['threadid'] = array();
	}

	public function fetchCanView($record)
	{
		$this->processUsers();
		return $this->fetchCanViewThread($record['contentid']);
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
		global $show;

		$threadinfo =& $this->content['thread'][$activity['contentid']];
		$activity['postdate'] = vbdate(vB::$vbulletin->options['dateformat'], $activity['dateline'], true);
		$activity['posttime'] = vbdate(vB::$vbulletin->options['timeformat'], $activity['dateline']);

		$threadinfo['preview'] = strip_quotes($threadinfo['pagetext']);
		$threadinfo['preview'] = htmlspecialchars_uni(fetch_censored_text(
			fetch_trimmed_title(strip_bbcode($threadinfo['preview'], false, true, true, true),
				vb::$vbulletin->options['as_snippet'])
		));

		$forumperms = fetch_permissions($threadinfo['forumid']);
		$show['threadcontent'] = ($forumperms & vB::$vbulletin->bf_ugp_forumpermissions['canviewthreads']);
		$userinfo = $this->fetchUser($activity['userid'], $threadinfo['postusername']);

		$templater = vB_Template::create($templatename);
			$templater->register('userinfo', $userinfo);
			$templater->register('activity', $activity);
			$templater->register('threadinfo', $threadinfo);
			$templater->register('foruminfo', vB::$vbulletin->forumcache[$threadinfo['forumid']]);
		return $templater->render();
	}
}

/*======================================================================*\
|| ####################################################################
|| # 
|| # CVS: $RCSfile$ - $Revision: 57655 $
|| ####################################################################
\*======================================================================*/