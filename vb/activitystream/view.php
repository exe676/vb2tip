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

/**
 * Class to view the activity stream
 *
 * @package	vBulletin
 * @version	$Revision: 57655 $
 * @date		$Date: 2012-01-09 12:08:39 -0800 (Mon, 09 Jan 2012) $
 */
class vB_ActivityStream_View
{
	/* Group contentids
	 *
	 * 	forum, post - collect postid and threadid, add threadid to next:
	 * 	forum, thread - collect threadid and forumid
	 * 	(and poll)
	 *
	 * 	cms, article
	 * 	cms, comment
	 *
	 * 	album, album
	 * 	album, photo
	 * 	album, comment
	 *
	 * 	blog, entry
	 * 	blog, comment
	 *
	 * 	socialgroup, discussion
	 * 	socialgroup, groupmessage
	 * 	socialgroup, group
	 * 	socialgroup, photo
	 * 	socialgroup, photocomment
	 *
	 * @param	array	Activity info
	 *
	 * @return	string	Class Name
	 */

	/**
	 * Hook for constructor.
	 *
	 * @var string
	 */
	private $hook_start = 'activity_view_start';

	/**
	 * Hook into each class before group
	 *
	 * @var string
	 */
	private $hook_group = 'activity_view_group';

	/**
	 * Hook into the UNION query
	 *
	 * @var string
	 */
	private $hook_union = 'activity_view_union_sql';

	/**
	 * Hook into before fetch
	 *
	 * @var string
	 */
	private $hook_beforefetch = 'activity_view_beforefetch';

	/**
	 * SQL WHERE conditions
	 *
	 * @var string
	 */
	private $wheresql = array(
		'stream.dateline <> 0'
	);

	/**
	 * SQL LIMIT conditions
	 *
	 * @var string
	 */
	private $limitsql = '';

	/**
	 * SQL Order By
	 *
	 * @var string
	 */
	private $orderby = 'dateline DESC';

	/**
	 * Perpage
	 *
	 * @var int
	 */
	private $perpage = 30;

	/**
	 * Ajax Refresh rate
	 *
	 * @var int
	 */
	private $refresh = 1;

	/**
	 * Grouping array for content
	 *
	 * @var string
	 */
	private $content = array();

	/**
	 * Group By
	 *
	 * @var string
	 */
	private $groupBy = '';

	/**
	 * List of classes used by this stream instance
	 *
	 * @var array
	 */
	private $classes = array();

	/*
	 * Retrieve subscriptions, a different query from the other filters
	 *
	 */
	private $setSubscriptions = false;

	/**
	 *
	 */
	private $setFilters = array();

	/*
	 * vbphrase
	 */
	protected $vbphrase = null;

	/**
	 * Constructor - set Options
	 *
	 */
	public function __construct(&$vbphrase)
	{
		$this->refresh = intval(vB::$vbulletin->options['as_refresh']);
		if (!$this->refresh)
		{
			$this->refresh = 1;
		}
		$this->vbphrase =& $vbphrase;
		$this->wheresql[] =  "stream.dateline <= " . TIMENOW;

		($hook = vBulletinHook::fetch_hook($this->hook_start)) ? eval($hook) : false;
	}

	/*
	 * Set Where Filter
	 *
	 */
	public function setWhereFilter($filtertype, $value = null, $argument = 0)
	{
		$this->setFilters[$filtertype] = 1;
		switch ($filtertype)
		{
			case 'ignoredusers':
				require_once(DIR . '/includes/functions_bigthree.php');
				$coventry = fetch_coventry();
				$ignorelist = array();
				if (trim(vB::$vbulletin->userinfo['ignorelist']))
				{
					$ignorelist = preg_split('/( )+/', trim(vB::$vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
				}
				if ($ignored = array_merge($coventry, $ignorelist))
				{
					$this->wheresql[] = "stream.userid NOT IN (" . implode(",", $ignored) . ")";
				}
				break;
			case 'minscore':
				if (!$value) { return; }
				$this->wheresql[] = "stream.score <= $value";
				break;
			case 'mindateline':
				if (!$value) { return; }
				$this->wheresql[] = "stream.dateline <= " . intval($value);
				break;
			case 'maxdateline':
				if (!$value) { return; }
				/* Don't put >= here */
				$this->wheresql[] = "stream.dateline > " . intval($value);
				break;
			case 'excludeid':
				if (!$value) { return; }
				$ids = explode(',', $value);
				$ids = array_map('intval', $ids);
				if ($ids)
				{
					$this->wheresql[] = "stream.activitystreamid NOT IN (" . implode(',', $ids) . ")";
				}
				break;
			case 'userid':
				if (!$value) { return; }
				if (!is_array($value))
				{
					$value = array($value);
				}
				$value = array_map('intval', $value);

				$this->wheresql[] = "stream.userid IN (" . implode(",", $value) . ")";
				break;
			case 'type':	// this only supports photo ..
				if (!$value) { return; }
				if ($photos = vB::$vbulletin->activitystream['photo'])
				{
					$this->wheresql[] = "stream.typeid IN (" . implode(", ", $photos) . ")";
				}
				else
				{
					$this->wheresql[] = "stream.typeid = 0";
				}
				break;
			case 'section':
				if (!$value) { return; }
				if ($sections = vB::$vbulletin->activitystream['enabled'][$value])
				{
					$this->wheresql[] = "stream.typeid IN (" . implode(", ", $sections) . ")";
				}
				else
				{
					$this->wheresql[] = "stream.typeid = 0";
				}
				break;
			case 'friends':
				if (!$value) { return; }
				if (vB::$vbulletin->options['socnet'] & vB::$vbulletin->bf_misc_socnet['enable_friends'] AND $friends = $this->fetchFriends($value))
				{
					if (!$friends)
					{
						$this->wheresql[] = "stream.userid = 0";
						return false;
					}
					else
					{
						$this->wheresql[] = "stream.userid IN (" . implode(",", $friends) . ")";
						return true;
					}
				}
				else
				{
					$this->wheresql[] = "stream.userid = 0";
					return false;
				}
				break;
			case 'all':
				if (!$value) { return; }
				if (!is_array($value))
				{
					$value = array($value);
				}
				$value = array_map('intval', $value);

				if (vB::$vbulletin->options['socnet'] & vB::$vbulletin->bf_misc_socnet['enable_friends'])
				{
					$friends = $this->fetchFriends($value);
					$value = array_merge($value, $friends);
				}

				$this->wheresql[] = "stream.userid IN (" . implode(",", $value) . ")";
				break;
		}
	}

	protected function fetchFriends($userid)
	{
		if (!is_array($userid))
		{
			$userid = array($userid);
		}

		static $result = array();
		if ($result)
		{
			return $result;
		}

		$userids = array_map('intval', $userid);
		$result = array();
		$friends = vB::$db->query("
			SELECT relationid
			FROM " . TABLE_PREFIX . "userlist
			WHERE
				userid IN (" . implode(",", $userids) . ")
					AND
				type = 'buddy'
					AND
				friend = 'yes'
		");
		while($friend = vB::$db->fetch_array($friends))
		{
			$result[] = $friend['relationid'];
		}

		return $result;
	}

	/*
	 * Set Pagenumber & perpage (More ....)
	 *
	 */
	public function setPage($pagenumber, $perpage = 30)
	{
		$pagenumber = intval($pagenumber);
		if (!$pagenumber)
		{
			$pagenumber = 1;
		}
		$perpage = intval($perpage);
		if (!$perpage OR $perpage > 30)
		{
			$perpage = 30;
		}
		$startat = $perpage * ($pagenumber - 1);

		$this->limitsql = "LIMIT {$startat}, {$perpage}";
		$this->perpage = $perpage;
	}

	/*
	 * Set Group by .. only works for date / photos
	 *
	 */
	public function setGroupBy($method)
	{
		$this->groupBy = $method;
	}

	/*
	 * Fetch subscribed item activity - only executed for viewing logged in user
	 *
	 * - Subscribed Threads (Posts)
	 * - Subscribed Groups (Discussions and Photos)
	 * - Subscribed Discussions (replies)
	 * - Subscribed Blog Entries (Comments)
	 * - Subscribed Blog Users (Entries)
	 * - Subscribed Events (unsupported at present)
	 * - Subscribed Forums (unsupported at present)
	 */
	protected function fetchSubscribeUnionSql()
	{
		$sqlbits = array();

		if (vB::$vbulletin->activitystream['forum_post']['enabled'])
		{
			$sqlbits[] = "
				### Threads ###
				SELECT stream.*, type.section, type.type
				FROM " . TABLE_PREFIX . "activitystream AS stream
				INNER JOIN " . TABLE_PREFIX . "activitystreamtype AS type ON (stream.typeid = type.typeid)
				INNER JOIN " . TABLE_PREFIX . "post AS p ON (p.postid = stream.contentid)
				INNER JOIN " . TABLE_PREFIX . "subscribethread AS st ON (
					p.threadid = st.threadid
						AND
					stream.typeid = " . intval(vB::$vbulletin->activitystream['forum_post']['typeid']) . "
						AND
					st.userid = " . vB::$vbulletin->userinfo['userid'] . "
				)
				" . ($this->wheresql ? "WHERE " . implode(" AND ", $this->wheresql) : "") . "
			";
		}

		/*
		 * Blog specific bits
		 */
		if (vB::$vbulletin->products['vbblog'])
		{
			if (vB::$vbulletin->activitystream['blog_comment']['enabled'])
			{
				$sqlbits[] = "
					### Blog Entries###
					SELECT stream.*, type.section, type.type
					FROM " . TABLE_PREFIX . "activitystream AS stream
					INNER JOIN " . TABLE_PREFIX . "activitystreamtype AS type ON (stream.typeid = type.typeid)
					INNER JOIN " . TABLE_PREFIX . "blog_text AS bt ON (bt.blogtextid = stream.contentid)
					INNER JOIN " . TABLE_PREFIX . "blog_subscribeentry AS se ON (
						bt.blogid = se.blogid
							AND
						stream.typeid = " . intval(vB::$vbulletin->activitystream['blog_comment']['typeid']) . "
							AND
						se.userid = " . vB::$vbulletin->userinfo['userid'] . "
					)
					" . ($this->wheresql ? "WHERE " . implode(" AND ", $this->wheresql) : "") . "
				";
			}

			/*
			 * The query below filters out any entries in the blog_subscribeentry table since they
			 * will be populated in the above query
			 */

			if( vB::$vbulletin->activitystream['blog_entry']['enabled'])
			{
				$sqlbits[] = "
					### Blog User###
					SELECT stream.*, type.section, type.type
					FROM " . TABLE_PREFIX . "activitystream AS stream
					INNER JOIN " . TABLE_PREFIX . "activitystreamtype AS type ON (stream.typeid = type.typeid)
					INNER JOIN " . TABLE_PREFIX . "blog AS blog ON (blog.blogid = stream.contentid)
					INNER JOIN " . TABLE_PREFIX . "blog_subscribeuser AS su ON (
						blog.userid = su.bloguserid
							AND
						stream.typeid = " . intval(vB::$vbulletin->activitystream['blog_entry']['typeid']) . "
							AND
						su.userid = " . vB::$vbulletin->userinfo['userid'] . "
					)
					" . ($this->wheresql ? "WHERE " . implode(" AND ", $this->wheresql) : "") . "
				";
			}
		}

		/*
		 * Social Group specific bits
		 */
		if (
			(vB::$vbulletin->options['socnet'] & vB::$vbulletin->bf_misc_socnet['enable_groups'])
				AND
			(vB::$vbulletin->userinfo['permissions']['socialgrouppermissions'] & vB::$vbulletin->bf_ugp_socialgrouppermissions['canviewgroups'])
		)
		{
			if (vB::$vbulletin->activitystream['socialgroup_groupmessage']['enabled'])
			{
				$sqlbits[] = "
					### Social Group Messages ###
					SELECT stream.*, type.section, type.type
					FROM " . TABLE_PREFIX . "activitystream AS stream
					INNER JOIN " . TABLE_PREFIX . "activitystreamtype AS type ON (stream.typeid = type.typeid)
					INNER JOIN " . TABLE_PREFIX . "groupmessage AS gm ON (gm.gmid = stream.contentid)
					INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS sd ON (
						gm.discussionid = sd.discussionid
							AND
						stream.typeid = " . intval(vB::$vbulletin->activitystream['socialgroup_groupmessage']['typeid']) . "
							AND
						sd.userid = " . vB::$vbulletin->userinfo['userid'] . "
					)
					" . ($this->wheresql ? "WHERE " . implode(" AND ", $this->wheresql) : "") . "
				";
			}

			if (vB::$vbulletin->activitystream['socialgroup_discussion']['enabled'])
			{
				$sqlbits[] = "
					### Social Group Discussions ###
					SELECT stream.*, type.section, type.type
					FROM " . TABLE_PREFIX . "activitystream AS stream
					INNER JOIN " . TABLE_PREFIX . "activitystreamtype AS type ON (stream.typeid = type.typeid)
					INNER JOIN " . TABLE_PREFIX . "discussion AS d ON (d.discussionid = stream.contentid)
					INNER JOIN " . TABLE_PREFIX . "subscribegroup AS sg ON (
						d.groupid = sg.groupid
							AND
						stream.typeid = " . intval(vB::$vbulletin->activitystream['socialgroup_discussion']['typeid']) . "
							AND
						sg.userid = " . vB::$vbulletin->userinfo['userid'] . "
					)
					" . ($this->wheresql ? "WHERE " . implode(" AND ", $this->wheresql) : "") . "
				";
			}

			if (vB::$vbulletin->activitystream['socialgroup_photo']['enabled'])
			{
				$contenttypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroup');
				$sqlbits[] = "
					### Social Group Photos ###
					SELECT stream.*, type.section, type.type
					FROM " . TABLE_PREFIX . "activitystream AS stream
					INNER JOIN " . TABLE_PREFIX . "activitystreamtype AS type ON (stream.typeid = type.typeid)
					INNER JOIN " . TABLE_PREFIX . "attachment AS a ON (a.attachmentid = stream.contentid AND a.contenttypeid = {$contenttypeid})
					INNER JOIN " . TABLE_PREFIX . "subscribegroup AS sg ON (
						a.contentid = sg.groupid
							AND
						stream.typeid = " . intval(vB::$vbulletin->activitystream['socialgroup_photo']['typeid']) . "
							AND
						sg.userid = " . vB::$vbulletin->userinfo['userid'] . "
					)
					" . ($this->wheresql ? "WHERE " . implode(" AND ", $this->wheresql) : "") . "
				";
			}
		}

		($hook = vBulletinHook::fetch_hook($this->hook_union)) ? eval($hook) : false;

		if (!$sqlbits)
		{
			$sqlbits[] = "
				SELECT stream.*
				FROM " . TABLE_PREFIX . "activitystream AS stream
				WHERE stream.activitystreamid = 0
			";
		}

		return vB::$vbulletin->db->query_read_slave("
			(" . implode(") UNION ALL (", $sqlbits) . ")
			ORDER BY dateline DESC
			{$this->limitsql}
		");
	}

	protected function fetchNormalSql()
	{
		/* INNER JOIN here on activystreamtype causes a temporary table .. !??! */
		return vB::$vbulletin->db->query_read_slave("
			SELECT stream.*, type.section, type.type
			FROM " . TABLE_PREFIX . "activitystream AS stream
			LEFT JOIN " . TABLE_PREFIX . "activitystreamtype AS type ON (stream.typeid = type.typeid)
			" . ($this->wheresql ? "WHERE " . implode(" AND ", $this->wheresql) : "") . "
			ORDER BY {$this->orderby}
			{$this->limitsql}
		");
	}

	public function setSubscriptionFilter()
	{
		$this->fetchSubscriptions = true;
	}

	protected function fetchRecords($sort = 'recent')
	{
		if (!$this->limitsql)
		{
			trigger_error('Must call perPage() before fetchStream()', E_USER_ERROR);
		}

		$records = array();
		$requiredExist = array();

		if ($this->fetchSubscriptions)
		{
			$activities = $this->fetchSubscribeUnionSql();
		}
		else
		{
			$activities = $this->fetchNormalSql();
		}

		while ($activity = vB::$vbulletin->db->fetch_array($activities))
		{
			/*
			 * The UNION query can generate duplicate records so assigning by streamid below takes care
			 * of the problem without resorting to DISTINCT in the query
			 */
			$records[] = $activity;
			if (!$activity['typeid'])
			{
				continue;
			}

			$classname = 'vB_ActivityStream_View_Perm_' . ucfirst($activity['section']) . '_' . ucfirst($activity['type']);
			if (!$this->classes[$classname])
			{
				$this->classes[$classname] = new $classname($this->content);
				($hook = vBulletinHook::fetch_hook($this->hook_group)) ? eval($hook) : false;
			}

			$this->classes[$classname]->group($activity);
			$requiredExist = array_merge($requiredExist, $this->classes[$classname]->fetchRequiredExist());
		}

		// Initiate Required classes that don't exist yet
		foreach (array_keys($requiredExist) AS $classname)
		{
			$this->classes[$classname] = new $classname($this->content);
		}

		$done = array();
		$classcount = count($this->classes);
		$count = 0;

		while ($classcount > count($done))
		{
			$count++;
			foreach ($this->classes AS $classname => $class)
			{
				if ($done[$classname])
				{
					continue;
				}

				/*
				 * Check that the required first classes have executed
				 * If not skip process() and reorder the classes
				 * Don't create a circular requirement as that will end badly
				 */
				if (!$class->verifyRequiredFirst($this->classes, $done))
				{
					continue;
				}

				$class->process();
				$done[$classname] = true;
			}
			if (($count + 1) > ($classcount * 2))
			{
				trigger_error('Runaway fetchStream()!', E_USER_ERROR);
			}
		}

		$return = array(
			'total'       => 0,
			'records'     => array(),
			'mindateline' => 0,
			'maxdateline' => 0,
			'minscore'    => 0,
			'minid'       => array(),
			'maxid'       => array(),
		);

		foreach ($records AS $activity)
		{
			$classname = 'vB_ActivityStream_View_Perm_' . ucfirst($activity['section']) . '_' . ucfirst($activity['type']);
			$class = $this->classes[$classname];

			if ($class->fetchCanView($activity))
			{
				$return['records'][] = $activity;
			}
			$return['total']++;

			if (!$return['maxdateline'])
			{
				$return['maxdateline'] = $activity['dateline'];
			}
			if ($return['maxdateline'] == $activity['dateline'])
			{
				$return['maxid'][] = $activity['activitystreamid'];
			}

			if ($sort == 'popular')
			{
				if ($return['minscore'] != $activity['score'])
				{
					$return['minid'] = array();
					$return['minscore'] = $activity['score'];
				}
			}
			else
			{
				if ($return['mindateline'] != $activity['dateline'])
				{
					$return['minid'] = array();
					$return['mindateline'] = $activity['dateline'];
				}
			}

			$return['minid'][] = $activity['activitystreamid'];
		}

		return $return;
	}

	/**
	 * Retrieve Activity Stream
	 *
	 */
	public function fetchStream($sort = 'recent')
	{
		$this->setWhereFilter('ignoredusers');

		$stop = false;
		$records = array();

		/* Fetch more records when we
		 * A. Have not set a 'maxdateline' filter (future request - new activity since page load)
		 * B. Received less than 50% (valid results) of our perpage value
		 * C. Have not requested more than 3 times already
		 */
		$iteration = 0;
		$totalcount = 0;
		$count = 0;
		$maxdateline = $mindateline = $minscore = 0;
		$maxid = $minid_score = $minid_dateline = array();
		while (!$stop AND $iteration < 4)
		{
			$result = $this->fetchRecords($sort);
			$records = array_merge($records, $result['records']);
			$totalcount += $result['total'];
			$count += count($result['records']);
			$iteration++;

			if (!$maxdateline)
			{
				$maxdateline = $result['maxdateline'];
			}
			if ($maxdateline == $result['maxdateline'])
			{
				$maxid = $result['maxid'];
			}

			if ($sort == 'popular')
			{
				if ($minscore != $result['minscore'])
				{
					$minid = array();
					$minscore = $result['minscore'];
				}
			}
			else
			{
				if ($mindateline != $result['mindateline'])
				{
					$minid = array();
					$mindateline = $result['mindateline'];
				}
			}
			$minid = $result['minid'];

			if ($count / $this->perpage > .5 OR $result['total'] < $this->perpage OR $this->setFilters['maxdateline'])
			{
				$stop = true;
			}
			else
			{
				if ($sort == 'popular')
				{
					$this->setWhereFilter('minscore', $result['minscore']);
				}
				else
				{
					$this->setWhereFilter('mindateline', $result['mindateline']);
				}
				$this->setWhereFilter('excludeid', implode(',', $result['minid']));
			}

			$moreresults = ($result['total'] == $this->perpage) ? 1 : 0;
		}

		$bits = array();
		$groupby = array();
		$count = 0;
		foreach ($records AS $activity)
		{
			$classname = 'vB_ActivityStream_View_Perm_' . ucfirst($activity['section']) . '_' . ucfirst($activity['type']);
			$class = $this->classes[$classname];
			$count++;

			// Call templater!
			if ($this->groupBy)
			{
				switch($this->groupBy)
				{
					case 'date':
					default:
						$foo = vB::$vbulletin->options['yestoday'];
						vB::$vbulletin->options['yestoday'] = 1;
						$date = vbdate(vB::$vbulletin->options['dateformat'], $activity['dateline'], true);
						vB::$vbulletin->options['yestoday'] = $foo;
						$templatename = 'activitystream_' . $activity['type'] . '_' . $this->groupBy . '_bit';
						$groupby[$date] .= $class->fetchTemplate($templatename, $activity, true);
				}
			}
			else
			{
				$templatename = 'activitystream_' . $activity['section'] . '_' . $activity['type'];
				$bits[] = $class->fetchTemplate($templatename, $activity, $sort == 'popular');
			}
		}

		if ($this->groupBy)
		{
			switch ($this->groupBy)
			{
				case 'date':
				default:
					foreach ($groupby AS $date => $bit)
					{
						$templater = vB_Template::create('activitystream_' . $this->groupBy . '_group');
							$templater->register('activitybits', $bit);
							$templater->register('date', $date);
						$bits[] = $templater->render();
					}
			}
		}

		if (count($minid) == $this->perpage AND vB::$vbulletin->GPC['minid'] AND $ids = explode(',', vB::$vbulletin->GPC['minid']))
		{
			$ids = array_map('intval', $ids);
			$minid = implode(',', array_merge($minid, $ids));
		}
		else
		{
			$minid = implode(',', $minid);
		}

		$return = array(
			'iteration'   => $iteration,
			'totalcount'  => $totalcount,
			'count'       => $count,
			'mindateline' => $mindateline,
			'maxdateline' => $maxdateline,
			'minid'       => $minid,
			'maxid'       => implode(',', $maxid),
			'moreresults' => $moreresults,
			'perpage'     => $this->perpage,
			'bits'        => $bits,
			'minscore'    => $minscore,
			'refresh'     => $this->refresh,
		);

		return $return;
	}

	protected function fetchMemberStreamSql($type, $userid)
	{
		switch($type)
		{
			case 'user':
			case 'asuser':
				$this->setWhereFilter('userid', $userid);
				break;
			case 'friends':
			case 'asfriend':
				if (!($this->setWhereFilter('friends', $userid)))
				{
					if (vB::$vbulletin->GPC['ajax'])
					{
						$this->processAjax(false);
					}
				}
				break;
			case 'subs':
			case 'assub':
				$this->setSubscriptionFilter();
				break;
			case 'photos':
			case 'asphoto':
				$this->setGroupBy('date');
				$this->setWhereFilter('userid', $userid);
				$this->setWhereFilter('type', 'photo');
				break;
			case 'all':
			case 'asasll':
			default:
				$type = 'all';
				$this->setWhereFilter('all', $userid);
		}

		return $type;
	}

	/*
	 * Process member stream
	 *
	 * @param	array	Userinfo
	 *
	 */
	public function processMemberStream($userid, $options, &$block_data)
	{
		global $show;

		$options['type'] = $this->fetchMemberStreamSql($options['type'], $userid);
		if (!$pagenumber)
		{
			$pagenumber = 1;
		}

		$block_data['selected_' . $options['type']] = 'selected';
		$block_data['pageinfo_all'] = array(
			'tab'  => 'activitystream',
			'type' => 'all'
		);
		$block_data['pageinfo_user'] = array(
			'tab'  => 'activitystream',
			'type' => 'user'
		);
		$block_data['pageinfo_subs'] = array(
			'tab'  => 'activitystream',
			'type' => 'subs'
		);
		$block_data['pageinfo_friends'] = array(
			'tab'  => 'activitystream',
			'type' => 'friends'
		);
		$block_data['pageinfo_photos'] = array(
			'tab'  => 'activitystream',
			'type' => 'photos'
		);
		$block_data['moreactivity'] = array(
			'tab'  => 'activitystream',
			'type' => $options['type'],
			'page' => $options['pagenumber'] + 1,
		);

		$show['asfriends'] = (vB::$vbulletin->options['socnet'] & vB::$vbulletin->bf_misc_socnet['enable_friends']);
		$this->setPage($pagenumber, vB::$vbulletin->options['as_perpage']);
		$result = $this->fetchStream();
		$block_data['mindateline'] = $result['mindateline'];
		$block_data['maxdateline'] = $result['maxdateline'];
		$block_data['minscore'] = $result['minscore'];
		$block_data['minid'] = $result['minid'];
		$block_data['maxid'] = $result['maxid'];
		$block_data['count'] = $result['count'];
		$block_data['totalcount'] = $result['totalcount'];
		$block_data['perpage'] = $result['perpage'];
		$block_data['refresh'] = $result['refresh'];

		$show['more_results'] = true;
		if ($result['totalcount'] < $result['perpage'])
		{
			$show['more_results'] = false;
		}

		$block_data['activitybits'] = '';
		foreach ($result['bits'] AS $bit)
		{
			$block_data['activitybits'] .= $bit;
		}
	}

	protected function processExclusions($sort = 'recent')
	{
		if ($sort == 'popular')
		{
			$this->setWhereFilter('minscore', vB::$vbulletin->GPC['minscore']);
		}
		if (vB::$vbulletin->GPC['mindateline'])
		{
			$this->setWhereFilter('mindateline', vB::$vbulletin->GPC['mindateline']);
		}
		if (vB::$vbulletin->GPC['maxdateline'])
		{
			$this->setWhereFilter('maxdateline', vB::$vbulletin->GPC['maxdateline']);
		}
		if (vB::$vbulletin->GPC['minid'])
		{
			$this->setWhereFilter('excludeid', vB::$vbulletin->GPC['minid']);
		}
		if (vB::$vbulletin->GPC['maxid'])
		{
			$this->setWhereFilter('excludeid', vB::$vbulletin->GPC['maxid']);
		}
	}

	/*
	 * Process member stream ajax
	 *
	 * @param	array	Userinfo
	 *
	 */
	public function processMemberStreamAjax()
	{
		vB::$vbulletin->input->clean_array_gpc('p', array(
			'userid'      => TYPE_UINT,
			'tab'         => TYPE_NOHTML,
			'mindateline' => TYPE_UNIXTIME,
			'maxdateline' => TYPE_UNIXTIME,
			'minscore'    => TYPE_NUM,
			'minid'       => TYPE_STR,
			'maxid'       => TYPE_STR,
			'pagenumber'  => TYPE_UINT,
			'perpage'     => TYPE_UINT,
		));

		vB::$vbulletin->GPC['ajax'] = 1;

		vB_dB_Assertor::init(vB::$vbulletin->db, vB::$vbulletin->userinfo);
		vB_ProfileCustomize::getUserTheme(vB::$vbulletin->GPC['userid']);
		$userhastheme = (vB_ProfileCustomize::getUserThemeType(vB::$vbulletin->GPC['userid']) == 1) ? 1 : 0;
		$showusercss = (vB::$vbulletin->userinfo['options'] & vB::$vbulletin->bf_misc_useroptions['showusercss']) ? 1 : 0;

		if ($userhastheme AND $showusercss)
		{
			define('AS_PROFILE', true);
		}

		$userinfo = verify_id('user', vB::$vbulletin->GPC['userid'], 1, 1);
		$this->fetchMemberStreamSql(vB::$vbulletin->GPC['tab'], $userinfo['userid']);
		$this->processExclusions();
		$this->setPage(1, vB::$vbulletin->GPC['perpage']);
		$result = $this->fetchStream();
		$this->processAjax($result);
	}

	/*
	 * Process the activity stream home page as well as handle ajax requests for further pages
	 *
	 */
	public function processActivityHome()
	{
		global $show;

		vB::$vbulletin->input->clean_array_gpc('r', array(
			'pagenumber'  => TYPE_UINT,
			'sortby'      => TYPE_NOHTML,
			'time'        => TYPE_NOHTML,
			'show'        => TYPE_NOHTML,
			'ajax'        => TYPE_BOOL,
			'mindateline' => TYPE_UNIXTIME,
			'maxdateline' => TYPE_UNIXTIME,
			'minscore'    => TYPE_NUM,
			'minid'       => TYPE_STR,
			'maxid'       => TYPE_STR,
		));

		$selected = array();
		$filters = array();

		$activitybits = '';

		/* I did not have time to make the filter options more dynamic. I wanted to base the presented filter options on the unqiue section contents of the
		 * activity stream datastore.  You will have to use the provided hooks to get your filter items in.
		 */

		$show['as_blog'] = (vB::$vbulletin->products['vbblog']);
		$show['as_cms'] = (vB::$vbulletin->products['vbcms']);
		$show['as_socialgroup'] = (
			vB::$vbulletin->options['socnet'] & vB::$vbulletin->bf_misc_socnet['enable_groups']
				AND
			vB::$vbulletin->userinfo['permissions']['socialgrouppermissions'] & vB::$vbulletin->bf_ugp_socialgrouppermissions['canviewgroups']
		);

		switch(vB::$vbulletin->GPC['sortby'])
		{
			case 'popular':
				$filters['sortby'] = $this->vbphrase['popular'];
				$this->orderby = 'score DESC, dateline DESC';
				break;
			default: // recent
				vB::$vbulletin->GPC['sortby'] = 'recent';
				$this->orderby = 'dateline DESC';
		}

		switch (vB::$vbulletin->GPC['show'])
		{
			case 'photos':
				if (vB::$vbulletin->GPC['sortby'] != 'popular')
				{
					$this->setGroupBy('date');
				}
				$this->setWhereFilter('type', 'photo');
				$filters['show'] = $this->vbphrase['photos'];
				break;
			case 'forum':
				$this->setWhereFilter('section', 'forum');
				$filters['show'] = $this->vbphrase['forums'];
				break;
			case 'cms':
				if ($show['as_cms'])
				{
					$this->setWhereFilter('section', 'cms');
					$filters['show'] = $this->vbphrase['articles'];
				}
				else
				{
					vB::$vbulletin->GPC['show'] = 'all';
				}
				break;
			case 'blog':
				if ($show['as_blog'])
				{
					$this->setWhereFilter('section', 'blog');
					$filters['show'] = $this->vbphrase['blogs'];
				}
				else
				{
					vB::$vbulletin->GPC['show'] = 'all';
				}
				break;
			case 'socialgroup':
				$this->setWhereFilter('section', 'socialgroup');
				$filters['show'] = $this->vbphrase['social_groups'];
				break;
			default: // all
				vB::$vbulletin->GPC['show'] = 'all';
		}

		switch(vB::$vbulletin->GPC['time'])
		{
			case 'today':
				$this->setWhereFilter('maxdateline', TIMENOW - 24 * 60 * 60);
				$filters['time'] = $this->vbphrase['last_24_hours'];
				break;
			case 'week':
				$this->setWhereFilter('maxdateline', TIMENOW - 7 * 24 * 60 * 60);
				$filters['time'] = $this->vbphrase['last_7_days'];
				break;
			case 'month':
				$this->setWhereFilter('maxdateline', TIMENOW - 30 * 24 * 60 *60);
				$filters['time'] = $this->vbphrase['last_30_days'];
				break;
			default: // anytime
				vB::$vbulletin->GPC['time'] = 'anytime';
		}

		$selected = array(
			vB::$vbulletin->GPC['time']   => ' class="selected" ',
			vB::$vbulletin->GPC['show']   => ' class="selected" ',
			vB::$vbulletin->GPC['sortby'] => ' class="selected" ',
		);

		$unselected = array(
			'popular'     => ' class="unselected" ',
			'recent'      => ' class="unselected" ',
			'anytime'     => ' class="unselected" ',
			'today'       => ' class="unselected" ',
			'week'        => ' class="unselected" ',
			'month'       => ' class="unselected" ',
			'all'         => ' class="unselected" ',
			'photos'      => ' class="unselected" ',
			'forum'       => ' class="unselected" ',
			'cms'         => ' class="unselected" ',
			'blog'        => ' class="unselected" ',
			'socialgroup' => ' class="unselected" ',
			'on'          => ' class="unselected" ',
			'off'         => ' class="unselected" ',
		);

		$unselected = array_diff_key($unselected, $selected);

		($hook = vBulletinHook::fetch_hook($this->hook_beforefetch)) ? eval($hook) : false;

		$arguments = array(
			'sortby' => array(
				'show=' . vB::$vbulletin->GPC['show'],
				'time=' . vB::$vbulletin->GPC['time'],
			),
			'time'   => array(
				'show=' . vB::$vbulletin->GPC['show'],
				'sortby=' . vB::$vbulletin->GPC['sortby'],
			),
			'show'   => array(
				'time=' . vB::$vbulletin->GPC['time'],
				'sortby=' . vB::$vbulletin->GPC['sortby'],
			)
		);

		foreach ($arguments AS $key => $values)
		{
			$arguments[$key] = implode("&amp;", $values);
		}

		$filter = array();
		foreach ($filters AS $type => $string)
		{
			$filter[] = array(
				'phrase'    => $string,
				'arguments' => $arguments[$type]
			);
		}
		$show['filterbar'] = !empty($filter);

		if (!vB::$vbulletin->GPC['pagenumber'])
		{
			vB::$vbulletin->GPC['pagenumber'] = 1;
		}

		$moreactivity = array(
			'type' => vB::$vbulletin->GPC['type'],
			'page' => vB::$vbulletin->GPC['pagenumber'] + 1,
		);

		$this->setPage(vB::$vbulletin->GPC['pagenumber'], vB::$vbulletin->options['as_perpage']);

		if (vB::$vbulletin->GPC['ajax'])
		{
			$this->processExclusions(vB::$vbulletin->GPC['sortby']);
			$result = $this->fetchStream(vB::$vbulletin->GPC['sortby']);
			$this->processAjax($result);
		}
		else
		{
			$result = $this->fetchStream(vB::$vbulletin->GPC['sortby']);
			$actdata = array(
				'mindateline' => $result['mindateline'],
				'maxdateline' => $result['maxdateline'],
				'minscore'    => $result['minscore'],
				'minid'       => $result['minid'],
				'maxid'       => $result['maxid'],
				'count'       => $result['count'],
				'totalcount'  => $result['totalcount'],
				'perpage'     => $result['perpage'],
				'time'        => vB::$vbulletin->GPC['time'],
				'show'        => vB::$vbulletin->GPC['show'],
				'sortby'      => vB::$vbulletin->GPC['sortby'],
				'refresh'     => $this->refresh,
			);

			$show['more_results'] = true;
			if ($result['totalcount'] < $result['perpage'])
			{
				$show['more_results'] = false;
			}

			foreach ($result['bits'] AS $bit)
			{
				$activitybits .= $bit;
			}

			$navbits = construct_navbits(array(
				vB::$vbulletin->options['forumhome'] . '.php?' . vB::$vbulletin->session->vars['sessionurl']=> $this->vbphrase['home'],
				'' => $this->vbphrase['activity_stream']
			));
			$navbar = render_navbar_template($navbits);

			$templater = vB_Template::create('activitystream_home');
				$templater->register_page_templates();
				$templater->register('selected', $selected);
				$templater->register('unselected', $unselected);
				$templater->register('activitybits', $activitybits);
				$templater->register('arguments', $arguments);
				$templater->register('filter', $filter);
				$templater->register('actdata', $actdata);
				$templater->register('navbar', $navbar);
				$templater->register('template_hook', $template_hook);
			print_output($templater->render());
		}
	}

	/*
	 * Output an ajax result of fetchStream()
	 *
	 */
	protected function processAjax($result)
	{
		require_once(DIR . '/includes/class_xml.php');
		$xml = new vB_AJAX_XML_Builder(vB::$vbulletin, 'text/xml');

		if (!$result)
		{
			$xml->add_tag('nada', '~~No Results Found~~');
			$xml->print_xml();
		}

		$xml->add_group('results');
		$xml->add_tag('count', $result['count']);
		$xml->add_tag('totalcount', $result['totalcount']);
		$xml->add_tag('minid', $result['minid']);
		$xml->add_tag('maxid', $result['maxid']);
		$xml->add_tag('mindateline', $result['mindateline']);
		$xml->add_tag('maxdateline', $result['maxdateline']);
		$xml->add_tag('minscore', $result['minscore']);
		$xml->add_tag('moreresults', $result['moreresults']);

		if ($result['bits'])
		{
			$xml->add_group('bits');
			foreach($result['bits'] AS $bit)
			{
				$xml->add_tag('bit', $bit);
			}
			$xml->close_group('bits');
		}

		$xml->close_group('results');
		$xml->print_xml();
	}
}

/*======================================================================*\
|| ####################################################################
|| # 
|| # CVS: $RCSfile$ - $Revision: 57655 $
|| ####################################################################
\*======================================================================*/
