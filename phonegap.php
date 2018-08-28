#!/usr/bin/php -q
<?php

$basepath = '/vz/appbuilder';
$imagepath = '/var/www/moodleapp';
require_once("$basepath/PhonegapBuildApi.php");


// Control floods, execute only one process at one time.
if (file_exists("$basepath/phonegap.pid")) {
$pid = file_get_contents("$basepath/phonegap.pid");
if (file_exists("/proc/$pid")) {
// TODO See if process running from last 2 hours.
error_log("found a running instance, exiting.");
exit(1);
} else {
error_log("previous process exited without cleaning pidfile, removing");
unlink("$basepath/phonegap.pid");
}
}

// Create new PID
$h = fopen("$basepath/phonegap.pid", 'w');
if ($h) fwrite($h, getmypid());
fclose($h);

shell_exec("date | xargs echo 'Start Processing phonegap' >> $basepath/debug.log");
for ($breaktime = 5 * 60, $difference = 0, $timestamp = time(); $difference < $breaktime; $difference = time() - $timestamp) { // run for maximum 5 minutes
if (!db_connect()) { // Make sure DB is connected
    sleep(50);
    continue; // TODO another way around?
}

// Create Accounts
$sql = mysql_query("SELECT * from mobile_app where build = 1 AND (anroidstage > 6 OR iosstage > 6)  AND (anroidstage < 9 OR iosstage < 9) order by id desc limit 50");
if ($sql) {
    while ($sql_result = mysql_fetch_array($sql)) {
        $id = intval($sql_result['id']);
        if (is_int($id) and $id > 0) {
            $appname = $sql_result['appname'];
            $siteurl = $sql_result['siteurl'];
            $description = $sql_result['description'];
            $icon = $sql_result['icon'];
            $logo = $sql_result['logo'];
            $splash = $sql_result['splash'];
            $appcolor = $sql_result['appcolor'];
	    $policyurl = $sql_result['policyurl'];
	    $urlscheme = $sql_result['urlscheme'];	            
	    $status = $sql_result['status'];
            $build = $sql_result['build'];
            $version = $sql_result['version'];
            $autoupgrade = $sql_result['autoupgrade'];

            $appid_and = $sql_result['anroidappid'];
            $appid_ios = $sql_result['iosappid'];

            $stage_ios = $sql_result['iosstage'];
            $path_ios = $sql_result['iospath'];

            $stage_and = $sql_result['anroidstage'];
            $path_and = $sql_result['anroidpath'];
            $lkey = $sql_result['lkey'];


            $api = new PhonegapBuildApi('JM7TZcPNGCsXThDti-9m');

            switch ($stage_and) {

                case 7: // Build for Android

                    $res = $api->createApplicationFromRepo('https://github.com/vidyamantra/customapps', array(
                        'title' => "$appname.$id.and",
                        'private' => false,
                        'tag' => "and$id",
                        'debug' => false,
                        'keys' => array(
                            'android' => array(
                                'id' => 176408,
                                'key_pw' => 'loginlogin',
                                'keystore_pw' => 'loginlogin'
                            ),
                        ),
                    ));

                    if ($api->success()) {
                        mysql_query("UPDATE mobile_app set anroidappid='" . $res['id'] . "' where id=$id");
                        mysql_query("UPDATE mobile_app set anroidstage='8' where id=$id");
                    } else {
                        var_dump($api->error());
			echo 'ERROR1'.$id;
                        mysql_query("UPDATE mobile_app set anroidpath='ERROR1' where id=$id");
                        mysql_query("UPDATE mobile_app set anroidstage='9' where id=$id");
                    }
                    break;

                case 8: // check status of android app
                    $res = $api->getApplication($appid_and);
                    if ($api->success()) {
                        if ($res['status']['android'] == 'complete') {
                            $res = $api->downloadApplicationPlatform($appid_and, PhonegapBuildApi::ANDROID);
                            if ($api->success()) {
                                $dl_path = "$imagepath/dl/$id$lkey.apk";
                                if ( file_put_contents("$dl_path", fopen($res['location'], 'r')) ) {
                                    $dl_path = "http://moodleapp.keytoschool.com/dl/$id$lkey.apk";
                                    mysql_query("UPDATE mobile_app set anroidpath='" . $dl_path . "' where id=$id");
#                                    $res = $api->deleteApplication($appid_and); // Now delete phonegap app
                                    #shell_exec("cd $basepath/build/$id/and ; /usr/bin/git push origin --delete and$id"); // Delete local git
                                    shell_exec("/bin/rm -rf $basepath/build/$id/and"); // Delete local repo
                                } else {
                                    mysql_query("UPDATE mobile_app set anroidpath='" . $res['location'] . "' where id=$id");
                                }
                                mysql_query("UPDATE mobile_app set anroidstage='9' where id=$id");
                            } else {
                                var_dump($api->error());
				echo 'ERROR2';
                            }
                        } elseif ($res['status']['android'] == 'error') {
                            mysql_query("UPDATE mobile_app set anroidpath='ERROR2' where id=$id");
                            mysql_query("UPDATE mobile_app set anroidstage='9' where id=$id");
                        } else {
                            var_dump($api->error());
			    echo 'ERROR3';
                        }
                    }

            }

            switch ($stage_ios) {

                case 7: // Build for IOS

                    $res = $api->createApplicationFromRepo('https://github.com/vidyamantra/customapps', array(
                        'title' => "$appname.$id.ios",
                        'private' => false,
                        'tag' => "ios$id",
                        'debug' => false,
                        'keys' => array(
                            'ios' => array(
                                'id' => 508401,
                                'password' => 'loginlogin'
                            ),
                        ),
                    ));

                    if ($api->success()) {
                        mysql_query("UPDATE mobile_app set iosappid='" . $res['id'] . "' where id=$id");
                        mysql_query("UPDATE mobile_app set iosstage='8' where id=$id");
                    } else {
                        var_dump($api->error());
			echo 'ERROR4';
                        mysql_query("UPDATE mobile_app set iospath='ERROR3' where id=$id");
                        mysql_query("UPDATE mobile_app set iosstage='9' where id=$id");
                    }

                    break;

                case 8: // check status of ios app
                    $res = $api->getApplication($appid_ios);
                    if ($api->success()) {
                        if ($res['status']['ios'] == 'complete') {
                            $res = $api->downloadApplicationPlatform($appid_ios, PhonegapBuildApi::IOS);
                            if ($api->success()) {
                                $dl_path = "$imagepath/dl/$id$lkey.ipa";
                                if ( file_put_contents("$dl_path", fopen($res['location'], 'r')) ) {
                                    $dl_path = "http://moodleapp.keytoschool.com/dl/$id$lkey.ipa";
                                    mysql_query("UPDATE mobile_app set iospath='" . $dl_path . "' where id=$id");
#                                    $res = $api->deleteApplication($appid_ios); // Now delete phonegap app
#                                    shell_exec("cd $basepath/build/$id/ios ; /usr/bin/git push origin --delete ios$id"); // Delete local git
                                    shell_exec("/bin/rm -rf $basepath/build/$id/ios"); // Delete local repo
                                } else {
                                    mysql_query("UPDATE mobile_app set iospath='" . $res['location'] . "' where id=$id");
                                }
                                mysql_query("UPDATE mobile_app set iosstage='9' where id=$id");
                                mysql_query("UPDATE mobile_app set status='1' where id=$id");
                                mysql_query("UPDATE mobile_app set build='0' where id=$id");
                            } else {
                                var_dump($api->error());
				echo 'ERROR5';
                            }
                        } elseif ($res['status']['ios'] == 'error') {
                            mysql_query("UPDATE mobile_app set iospath='ERROR4' where id=$id");
                            mysql_query("UPDATE mobile_app set iosstage='9' where id=$id");
                        } else {
                            var_dump($api->error());
			    echo 'ERROR6';
                        }
                    }
            }
        }
    }
}
    sleep(45);
}


// Delete PID in end.
    unlink("$basepath/phonegap.pid");


// FUNCTIONS

    function db_connect()
    {
// MySQL Connect
    $server_con = mysql_connect('localhost', 'appbuilder', 'aomqIPEthU2KUEcvF8qx');
        if (!$server_con || !mysql_select_db("appbuilder", $server_con)) {
            return false;
        }
        return true;
    }

