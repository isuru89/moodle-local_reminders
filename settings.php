<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package local_reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {

    require_once($CFG->dirroot.'/local/reminders/lib.php');
    
    $settings = new admin_settingpage('local_reminders', get_string('admintreelabel', 'local_reminders'));
    $ADMIN->add('localplugins', $settings);
    
    // load all roles in the moodle
    $systemcontext = context_system::instance();
    $allroles = role_fix_names(get_all_roles(), $systemcontext, ROLENAME_ORIGINAL);
    $rolesarray = array();
    if (!empty($allroles)) {
        foreach ($allroles as $arole) {
            $rolesarray[$arole->shortname] = ' '.$arole->localname;
            //echo '[SHORTNAME: '. $arole->shortname.', ROLENAME: '.$arole->localname.'<br>';
        }
    }
    
    // default settings for recieving reminders according to role
    $defaultrolesforcourse = array('student' => 1);
    $defaultrolesforactivity = array('student' => 1, 'editingteacher' => 1);
    
    // adds a checkbox to enable/disable sending reminders
    $settings->add(new admin_setting_configcheckbox('local_reminders_enable', 
            get_string('enabled', 'local_reminders'), 
            get_string('enableddescription', 'local_reminders'), 1));
    
    $settings->add(new admin_setting_configtext('local_reminders_messagetitleprefix',
            get_string('messagetitleprefix', 'local_reminders'),
            get_string('messagetitleprefixdescription', 'local_reminders'), 'Moodle-Reminder'));
    
    $choices = array(REMINDERS_SEND_ALL_EVENTS => get_string('filtereventssendall', 'local_reminders'),
                     REMINDERS_SEND_ONLY_VISIBLE => get_string('filtereventsonlyvisible', 'local_reminders'));
    
    $settings->add(new admin_setting_configselect('local_reminders_filterevents',
            get_string('filterevents', 'local_reminders'), 
            get_string('filtereventsdescription', 'local_reminders'),
            REMINDERS_SEND_ONLY_VISIBLE, $choices));
    
    $daysarray = array('days7' => ' '.get_string('days7', 'local_reminders'), 
                       'days3' => ' '.get_string('days3', 'local_reminders'),
                       'days1' => ' '.get_string('days1', 'local_reminders'));
    
    // default settings for each event type
    $defaultsite = array('days7' => 0,'days3' => 1,'days1' => 0);
    $defaultuser = array('days7' => 0,'days3' => 0,'days1' => 1);
    $defaultcourse = array('days7' => 0,'days3' => 1,'days1' => 0);
    $defaultgroup = array('days7' => 0,'days3' => 1,'days1' => 0);
    $defaultdue = array('days7' => 0,'days3' => 1,'days1' => 0);
    
    // add days selection for site events
    $settings->add(new admin_setting_heading('local_reminders_site_heading', 
            get_string('siteheading', 'local_reminders'), ''));
    
    $settings->add(new admin_setting_configmulticheckbox2('local_reminders_siterdays', 
            get_string('reminderdaysahead', 'local_reminders'), 
            get_string('explainsiteheading', 'local_reminders'),
            $defaultsite , $daysarray));
    
    // add days selection for user related events.
    $settings->add(new admin_setting_heading('local_reminders_user_heading', 
            get_string('userheading', 'local_reminders'), ''));
    
    $settings->add(new admin_setting_configmulticheckbox2('local_reminders_userrdays', 
            get_string('reminderdaysahead', 'local_reminders'), 
            get_string('explainuserheading', 'local_reminders'),
            $defaultuser, $daysarray));
    
    // add days selection for course related events.
    $settings->add(new admin_setting_heading('local_reminders_course_heading', 
            get_string('courseheading', 'local_reminders'), ''));
    
    $settings->add(new admin_setting_configmulticheckbox2('local_reminders_courserdays', 
            get_string('reminderdaysahead', 'local_reminders'), 
            get_string('explaincourseheading', 'local_reminders'), 
            $defaultcourse, $daysarray));
    
    $settings->add(new admin_setting_configmulticheckbox2('local_reminders_courseroles',
            get_string('rolesallowedfor', 'local_reminders'),
            get_string('explainrolesallowedfor', 'local_reminders'),
            $defaultrolesforcourse, $rolesarray));
    
    // add days selection for due related events coming from activities in a course.
    $settings->add(new admin_setting_heading('local_reminders_due_heading', 
            get_string('dueheading', 'local_reminders'), ''));
    
    $activitychoices = array(REMINDERS_ACTIVITY_BOTH => get_string('activityremindersboth', 'local_reminders'),
                             REMINDERS_ACTIVITY_ONLY_OPENINGS => get_string('activityremindersonlyopenings', 'local_reminders'),
                             REMINDERS_ACTIVITY_ONLY_CLOSINGS => get_string('activityremindersonlyclosings', 'local_reminders'));
    
    $settings->add(new admin_setting_configselect('local_reminders_duesend',
            get_string('sendactivityreminders', 'local_reminders'), 
            get_string('explainsendactivityreminders', 'local_reminders'),
            REMINDERS_ACTIVITY_BOTH, $activitychoices));
    
    $settings->add(new admin_setting_configmulticheckbox2('local_reminders_duerdays', 
            get_string('reminderdaysahead', 'local_reminders'), 
            get_string('explaindueheading', 'local_reminders'), 
            $defaultdue, $daysarray));
 
    $settings->add(new admin_setting_configmulticheckbox2('local_reminders_activityroles',
            get_string('rolesallowedfor', 'local_reminders'),
            get_string('explainrolesallowedfor', 'local_reminders'),
            $defaultrolesforactivity, $rolesarray));
    
    // add group related events
    $settings->add(new admin_setting_heading('local_reminders_group_heading', 
            get_string('groupheading', 'local_reminders'), ''));
    
    $settings->add(new admin_setting_configcheckbox('local_reminders_groupshowname', 
            get_string('groupshowname', 'local_reminders'), 
            get_string('explaingroupshowname', 'local_reminders'), 1));
    
    $settings->add(new admin_setting_configmulticheckbox2('local_reminders_grouprdays', 
            get_string('reminderdaysahead', 'local_reminders'), 
            get_string('explaingroupheading', 'local_reminders'), 
            $defaultgroup, $daysarray));
    
}