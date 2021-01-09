=== Reminders "Local Plugin" ===

Author:    Isuru Madushanka Weerarathna (uisurumadushanka89@gmail.com)
Blog:      https://medium.com/@isuru89
Copyright: 2020 Isuru Madushanka Weerarathna
License:   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
Version:   2.2.2

== Introduction ==
This plugin will create a set of reminders for Moodle calendar events and will send them automatically
to relevant users in timely manner. Reminders are very useful for both students as well as teachers
to recall their scheduled event before the actual moment.

== Requirements ==
    This plugin has been developed in Moodle >2.2 successfully tested on a simple local server.
    This plugin (< v2.0) should be working any Moodle version greater than or equal to v2.
    Moodle logging must be enabled to operate properly. (Only if you are using a version 2.6 or below)

== Installation ==
1. Fetch the plug-in from following location.
     + https://moodle.org/plugins/view.php?plugin=local_reminders
     + Download a suitable version compatible with your Moodle server.
2. Goto the Moodle root directory and go inside 'local' directory.
3. Create a folder named 'reminders'.
4. Now extract the downloaded zip file inside to this folder. After it is extracted,
    all files and folders must compliance with given structure as shown in below.
5. Log into the Moodle site as the admin user. Usually the new plug-in must be identified
    and notified you when logged-in. If not then goto Site Administration -> Notifications
    to install the local plug-in.
6. Now you can change the plug-in specific settings via Site Administration -> Plugins -> Local Plugins -> Reminders.

== Change Log ==
v2.2.2
    + Fixed plain text message format for notifications in mobile device (#87)
    + Fixed legacy cron duplication issue (#91)
    + Added missing language string (#94)
v2.2.1
    + No reminders for hidden courses (#78)
    + Fixed coursecat deprecation warning (#76)
    + Fixed no-reply address not correctly being in the reminder emails (#77)
    + Fixed disabled course reminders are still sending issue (#84)
v2.2
    + Option to show/hide activity plugin name in reminder email subject (#71)
    + Support reminder customization for course and BigBlueButton events (#69, #70)
v2.1.2
    + Php 7.0 compatibility (#66)
v2.1.1
    + Check for custom completion status of activities (#62)
v2.1
    + eminder interval customization per activity instance (#57)
    + Support for course category events (#40)
v2.0 (Only works in Moodle 3.5 or above)
    + Ability to send reminders after event completed for overdue students (#3)
    + Ability to enable/disable reminders per activity (#48)
    + Reminder email messages when calendar event created/changed/removed (#21)
    + Prevent sending more email reminders once a student has completed the activity
    + Added timezone/location to the email due dates (#32)
    + Improved email style (#31)
    + Additional cron task to clean old reminder data (#37)
    + Send reminders only to students who can submit assignment (#47)
v1.7
    + Excluded suspended users from course/activity events (#27)
    + Renamed admin cron function so that conflicting with legacy (#26)
    + Totara conflicting class name issue fixed (#25)
    + Support for moodle v3.8
v1.6.2
    + Fixed issue of conflicting class name
v1.6.1
    + Fix login redirect loop in Moodle v3.5, 3.6 and 3.7.
v1.6
    + Support for Moodle v3.5 and above.
    + Migrated to new Moodle task API.
v1.5.1
    + Fixed a bug where group reminders are not assigned when the event instance is empty
v1.5
    + support for moodle 3.0+
    + Ability to change mail sent user through configurations (#14)
    + Notice: undefined variable when opening admin settings page in Moodle 2.9 (#12)
    + Event reminders sent for individual quiz overrides (#11)
    + Fix time formatting when user has set 24hour format in calendar preferences
    + Fix cron errors resulting from new role (thanks to [colin-umn]: https://github.com/colin-umn)
    + Fix cron error caused by $courseroleids (thanks to [cdsmith-umn]: https://github.com/cdsmith-umn)

v1.4.2
    + support for moodle 2.9+
    + ability to specify a custom schedule for sending reminders for any event type.

v1.4.1
    + support for moodle 2.8 (thanks to [jojoob]: https://github.com/jojoob)
    + course specific settings added for reminders (thanks to [jojoob]: https://github.com/jojoob)
v1.4
    + now works in Moodle 2.7.*
    + fixed bug sending reminders repeatedly to users.

v1.3.1
    + bug fixes
    + prevent users receiving alerts for an activity that they can't see. (Contributed by Julian Boulen)
    + exception handling

v1.3
    + now works in Moodle 2.5.*
    + time zone adjustment based on recipient of the reminder
    + reminder messages for activities (such as quizes, assignments, etc) are enhanced and
      visibility of some fields are restricted according to the constraints of such activities
      (eg: showing description field)

v1.2
    + now works in Moodle 2.4.*
    + fixed bug when sending reminders based on groups
    + group reminder message content has been made richer by including course and activity details.
    + added a setting to define the prefix for messages being sent, and
        added another setting to define to show/hide group name in the group reminder message.
    + cron cycle interval for this plugin has been reduced from 1-hour to 15-minutes.
v1.1
    + fixed bug of repeatedly sending reminders for same event.
    + removed 'Only hidden events from calendar' option from the settings page.
    + removed unused constants from the plugin.
    + improved cron trace of the plugin for ignored events.

v1.0.1
    + changed default settings
    + removed usage of deprecated functions

== Configurations ==
Since Moodle v3.5
To change the schedule of this plugin, go to Site administration -> Server -> Scheduled tasks,
and find the Local Reminders item from the list.
Click the settings icon, and change the cron expression as desired.


Before Moodle v3.5:
If you want to change the cron cycle frequency, open the version.php file in the plug-in's root
directory and change the value for $plugin->cron. This value must be indicated by seconds. The
default value is 3600 seconds (i.e. 1 hour).
This frequency will be affected to the performance of Moodle cron system. Too much small value
will be an additional overhead while large value will be a problem of flooding the message
interface because of trying to send too many reminders at once.

== Folder Structure ==
All following folders/files must be put in to the local directory of Moodle root folder to work properly.

    /reminders/classes/task/send_reminders.php
    /reminders/contents/activity_handlers.class.php
    /reminders/contents/course_reminder.class.php
    /reminders/contents/due_reminder.class.php
    /reminders/contents/group_reminder.class.php
    /reminders/contents/site_reminder.class.php
    /reminders/contents/user_reminder.class.php
    /reminders/db/access.php
    /reminders/db/install.php
    /reminders/db/install.xml
    /reminders/db/messages.php
    /reminders/db/tasks.php
    /reminders/db/upgrade.php
    /reminders/lang/en/local_reminders.php
    /reminders/lang/de/local_reminders.php
    /reminders/lang/fr/local_reminders.php
    /reminders/coursesettings_form.php
    /reminders/coursesettings.php
    /reminders/lib.php
    /reminders/reminder.class.php
    /reminders/settings.php
    /reminders/version.php
    /reminders/README.txt

== Feedback ==
You can tell about this plug-in directly to me (uisurumadushanka89@gmail.com) or
to the Moodle contributed tracker (http://tracker.moodle.org/browse/CONTRIB-3647).

-Good Luck!-
