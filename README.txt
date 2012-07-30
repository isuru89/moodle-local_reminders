=== Reminders "Local Plugin" ===

Author:    Isuru Madushanka Weerarathna (uisurumadushanka89@gmail.com)
Blog:      http://uisurumadushanka89.blogspot.com
Copyright: 2012 Isuru Madushanka Weerarathna
License:   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
Version:   1.0

== Introduction ==
This plugin will create a set of reminders for Moodle calendar events and will send them automatically
to relevant users in timely manner. Reminders are very useful for both students as well as teachers 
to recall their scheduled event before the actual moment.

== Requirements ==
This plugin has been developed in Moodle 2.2 and successfully tested on a simple local server.
This plugin should be working any Moodle version greater than or equal to v2.0.	
	
== Installation ==
1. Fetch the plug-in from following location.
     + Goto https://github.com/isuru89/moodle-reminders-for-calendar-events and download the 
       repository as a zip file.
2. Goto the Moodle root directory and go inside 'local' directory.
3. Create a folder named 'reminders'.
4. Now extract the downloaded zip file inside to this folder. After it is extracted,
    all files and folders must compliance with given structure as shown in below.
5. Log into the Moodle site as the admin user. Usually the new plug-in must be identified
    and notified you when logged-in. If not then goto Site Administration -> Notifications
    to install the local plug-in.
6. Now you can change the plug-in specific settings via Site Administration -> Plugins -> Local Plugins -> Reminders.

== Configurations ==
If you want to change the cron cycle frequency, open the version.php file in the plug-in's root
directory and change the value for $plugin->cron. This value must be indicated by seconds. The
default value is 3600 seconds (i.e. 1 hour).
This frequency will be affected to the performance of Moodle cron system. Too much small value
will be an additional overhead while large value will be a problem of flooding the message
interface because of trying to send too many reminders at once.

== Folder Structure ==
All following folders/files must be put in to the local directory of Moodle root folder to work properly.

	/reminders/contents/course_reminder.class.php
	/reminders/contents/due_reminder.class.php
	/reminders/contents/group_reminder.class.php
	/reminders/contents/site_reminder.class.php
	/reminders/contents/user_reminder.class.php
	/reminders/db/access.php
	/reminders/db/install.php
	/reminders/db/messages.php
	/reminders/db/upgrade.php
	/reminders/lang/en/local_reminders.php
	/reminders/lib.php
	/reminders/reminder.class.php
	/reminders/settings.php
	/reminders/version.php
	/reminders/README.txt

== Feedback ==
You can tell about this plug-in directly to me (uisurumadushanka89@gmail.com) or 
to the Moodle contributed tracker (http://tracker.moodle.org/browse/CONTRIB-3647).

-Good Luck!-