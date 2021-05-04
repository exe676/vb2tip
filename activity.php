<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.0 
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2012 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'activity');
define('CSRF_PROTECTION', true);
if ($_POST['ajax'] == 1)
{
	define('LOCATION_BYPASS', 1);
	define('NOPMPOPUP', 1);
	define('NONOTICES', 1);
}

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'activitystream',
	'user'
);

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
	'blogcategorycache',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'activitystream_home',
	'activitystream_album_album',
	'activitystream_album_comment',
	'activitystream_album_photo',
	'activitystream_calendar_event',
	'activitystream_date_group',
	'activitystream_photo_date_bit',
	'activitystream_forum_post',
	'activitystream_forum_thread',
	'activitystream_forum_visitormessage',
	'activitystream_socialgroup_discussion',
	'activitystream_socialgroup_group',
	'activitystream_socialgroup_groupmessage',
	'activitystream_socialgroup_photo',
	'activitystream_socialgroup_photocomment',
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

if (STYLE_TYPE == 'mobile')
{
	exec_header_redirect('forum.php' . $vbulletin->session->vars['sessionurl_q']);
}

if ($_POST['do'] == 'loadactivitytab')
{
	$activity = new vB_ActivityStream_View($vbphrase);
	$activity->processMemberStreamAjax();
}
else
{
	$activity = new vB_ActivityStream_View($vbphrase);
	$activity->processActivityHome();
}

/*======================================================================*\
|| ####################################################################
|| # 
|| # CVS: $RCSfile$ - $Revision: 16016 $
|| ####################################################################
\*======================================================================*/
