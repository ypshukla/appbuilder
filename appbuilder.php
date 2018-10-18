#!/usr/bin/php -q
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$basepath = '/var/www/html/appbuilder';
$appsourceori = "$basepath/source/moodlemobile2";
$imagepath = '/var/www/html/moodleapp';


// Control floods, execute only one process at one time.
if (file_exists("$basepath/appbuilder.pid")) {
    $pid = file_get_contents("$basepath/appbuilder.pid");
    if (file_exists("/proc/$pid")) {
        // TODO See if process running from last 2 hours.
        error_log("found a running instance, exiting.");
        exit(1);
    } else {
        error_log("previous process exited without cleaning pidfile, removing");
        unlink("$basepath/appbuilder.pid");
    }
}

// Create new PID
$h = fopen("$basepath/appbuilder.pid", 'w');
if ($h) fwrite($h, getmypid());
fclose($h);

shell_exec("date | xargs echo 'Start Processing appbuilder' >> $basepath/debug.log");
for ($breaktime = 5 * 60, $difference = 0, $timestamp = time(); $difference < $breaktime; $difference = time() - $timestamp) { // run for maximum 5 minutes
    if (!db_connect()) { // Make sure DB is connected
        sleep(50);
        continue; // TODO another way around?
    } else{
        $con = db_connect();
    }

    // Create Accounts
    $sql = mysqli_query($con,"SELECT * from mobile_app where build = 1 and anroidstage < 7 order by id desc limit 50");
    if ($sql) {
        while ($sql_result = mysqli_fetch_array($sql)) {
            $id = intval($sql_result['id']);
            if (is_int($id) and $id > 0) {
                $appname = $sql_result['appname'];
                $siteurl = $sql_result['siteurl'];
                $description = $sql_result['description'];
                $icon = $sql_result['icon'];
                $logo = $sql_result['logo'];
                $splash = $sql_result['splash'];
                $tplogo = $sql_result['tplogo'];
                $appcolor = $sql_result['appcolor'];
				$policyurl = $sql_result['policyurl'];
				$urlscheme = $sql_result['urlscheme'];                
				$status = $sql_result['status'];
                $build = $sql_result['build'];
                $version = $sql_result['version'];
                $autoupgrade = $sql_result['autoupgrade'];
                $stage = $sql_result['anroidstage'];
                $appsource = "$basepath/source/$id";

                shell_exec("echo $appname >> $basepath/debug.log");

                switch ($stage) {

                    case 0: // changes in json file
                    case 1: // changes in json file

                        shell_exec("/bin/mkdir -p $appsource");
                        shell_exec("/bin/rm -rf $appsource/*");
                        shell_exec("/bin/cp -R $appsourceori/* $appsource/");

                        $jsonpath = "$appsource/src/config.json";
                        $string = file_get_contents($jsonpath);
                        $string = json_decode($string, true);
                        $string['app_id'] = "com.vidyamantra.cmoodleapp$id";
                        $string['appname'] = "$appname";
                        $string['desktopappname'] = "$appname Desktop";
                        $string['customurlscheme'] = "$urlscheme";
                        $string['siteurl'] = "$siteurl";
						$string['privacypolicy'] = "$policyurl";
                        $string = json_encode($string, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        file_put_contents($jsonpath, $string);
                        mysqli_query($con,"UPDATE mobile_app set anroidstage='2' where id=$id");

                    case 2: // changes in xml file
                        // Get values from json file

                        $jsonpath = "$appsource/src/config.json";
                        $string = file_get_contents($jsonpath);
                        $string = json_decode($string, true);
                        // Load xml file
                        $xmlpaths=array("$appsource/config.xml");
                        foreach ($xmlpaths as $xmlpath) {
                        $xml = simplexml_load_file($xmlpath);
                        // Make changes
                        $xml->attributes()->id = $string['app_id'];
                        $xml->attributes()->versionCode = $string['versioncode'];
                        $xml->attributes()->version = $string['versionname'];
                        $xml->name = $appname;
                        $xml->description = $description;
                        $xml->author = 'Vidya Mantra EduSystems Pvt. Ltd.';
                        $xml->author->attributes()->href = 'http://moodleapp.keytoschool.com';
                        $xml->author->attributes()->email = 'sales@vidyamantra.com';
                        // Save as dom
                        $dom_sxe = dom_import_simplexml($xml);
                        $dom = new DomDocument('1.0', 'UTF-8');
                        $dom_sxe = $dom->importNode($dom_sxe, true);
                        $dom_sxe = $dom->appendChild($dom_sxe);
                        // save as xml file
                        $dom->Save($xmlpath);
                        }
                        
                        shell_exec("/bin/sed -i '121s/moodlemobile/$urlscheme/' $appsource/config.xml");
                        
                        mysqli_query($con,"UPDATE mobile_app set anroidstage='3' where id=$id");

                    case 3: // Replacing images and generating images
                        $icon = $sql_result['icon'];
                        $splash = $sql_result['splash'];
                        $logo = $sql_result['logo'];

                        shell_exec("/bin/rm -f $appsource/resources/splash.png");
                        shell_exec("/bin/rm -f $appsource/resources/android/icon.png");
                        shell_exec("/bin/rm -f $appsource/resources/ios/icon.png");
                        shell_exec("/bin/rm -f $appsource/src/assets/img/login_logo.png");
                        shell_exec("/bin/rm -f $appsource/src/assets/img/splash_logo.png");

                        shell_exec("/bin/cp $imagepath/$splash $appsource/resources/splash.png");
                        shell_exec("/bin/cp $imagepath/$icon $appsource/resources/android/icon.png");
                        shell_exec("/bin/cp $imagepath/$icon $appsource/resources/ios/icon.png");
                        shell_exec("/bin/cp $imagepath/$tplogo $appsource/src/assets/img/splash_logo.png");
                        shell_exec("/bin/cp $imagepath/$logo $appsource/src/assets/img/login_logo.png");

                        shell_exec("cd $appsource ; /usr/bin/ionic cordova resources");

                        mysqli_query($con,"UPDATE mobile_app set anroidstage='4' where id=$id");


                    case 4: // Changes in CSS and run gulp
                        $csspath = "$appsource/src/theme/variables.scss";
                        $css = file_get_contents($csspath);
                        #$css = preg_replace('/\$positive: #([0-9a-zA-Z])+;/', "\$positive: $appcolor; \n \$bar-content-bg: $appcolor;", $css);
                        $css = preg_replace('/\$orange:\s+#([0-9a-zA-Z])+;/', "\$orange: $appcolor;", $css);
                        $css = preg_replace('/\$core-color-init-screen:\s+#([0-9a-zA-Z])+;/', "\$core-color-init-screen: $appcolor;", $css);
                        $css = preg_replace('/\$core-color-init-screen-alt:\s+#([0-9a-zA-Z])+;/', "\$core-color-init-screen-alt: $appcolor;", $css);
                        file_put_contents($csspath, $css);
								
                        // shell_exec("cd $appsource ; /usr/bin/gulp");
                        shell_exec("cd $appsource ; /usr/bin/gulp");
                        shell_exec("cd $appsource ; /usr/bin/npm run ionic:build -- --prod");

                        mysqli_query($con,"UPDATE mobile_app set anroidstage='5' where id=$id");

                    case 5: // Prepare build repo from source
                        // Create android repo
                        shell_exec("/bin/mkdir -p $basepath/build/$id/and");
                        shell_exec("/bin/rm -rf $basepath/build/$id/and/*");
                        shell_exec("/bin/cp -R $appsource/config.xml $basepath/build/$id/and/");
                        shell_exec("/bin/cp -R $appsource/www/* $basepath/build/$id/and/");
                        shell_exec("/bin/cp -R $appsource/resources $basepath/build/$id/and/");
                        shell_exec("/bin/cp -R $appsource/resources/android/icon.png $appsource/resources/splash.png $basepath/build/$id/and/");

                        // Make changes in config.xml
                        /* $path = "$basepath/build/$id/and/config.xml";
                        $string = file_get_contents($path);
                        $string = preg_replace("/<application android:debuggable=\"true\"\/>/", "<application android:debuggable=\"false\"/>", $string);
                        file_put_contents($path, $string);

                        // Make changes in config.xml
                        $path = "$basepath/build/$id/and/core/lib/log.js";
                        $string = file_get_contents($path);
                        $string = preg_replace("/constant\(\'mmCoreLogEnabledDefault\'\, true\)/", "constant('mmCoreLogEnabledDefault', false)", $string);
                        file_put_contents($path, $string); */

                        // Create ios repo
                        shell_exec("/bin/mkdir -p $basepath/build/$id/ios");
                        shell_exec("/bin/cp -R $basepath/build/$id/and/* $basepath/build/$id/ios/");
											
                        mysqli_query($con,"UPDATE mobile_app set anroidstage='6' where id=$id");

						case 6: // Prepare android git repo
                        /*shell_exec("/usr/bin/git init $basepath/build/$id/and");
                        shell_exec("cd $basepath/build/$id/and ; /usr/bin/git remote add origin git@github.com:ypshukla/phonegapbuild.git");
                        shell_exec("cd $basepath/build/$id/and ; /usr/bin/git checkout -b and$id");
                        shell_exec("cd $basepath/build/$id/and ; /usr/bin/git add -A");
						shell_exec("cd $basepath/build/$id/and ; /usr/bin/git commit -m \"and$id\"");
						shell_exec("cd $basepath/build/$id/and ; /usr/bin/git push -u origin and$id");*/
						
						// Prepare ios git repo
                        /*shell_exec("/usr/bin/git init $basepath/build/$id/ios");
                        shell_exec("cd $basepath/build/$id/ios ; /usr/bin/git remote add origin git@github.com:ypshukla/phonegapbuild.git");
                        shell_exec("cd $basepath/build/$id/ios ; /usr/bin/git checkout -b ios$id");
                        shell_exec("cd $basepath/build/$id/ios ; /usr/bin/git add -A");
                        shell_exec("cd $basepath/build/$id/ios ; /usr/bin/git commit -m \"ios$id\"");
                        shell_exec("cd $basepath/build/$id/ios ; /usr/bin/git push -u origin ios$id");*/

                        //shell_exec("/bin/rm -rf $appsource");

                        mysqli_query($con,"UPDATE mobile_app set anroidstage='7' where id=$id");
                        //mysqli_query($con,"UPDATE mobile_app set iosstage='7' where id=$id");
                        
                }
            }
            //return $result;
        }
    }
    sleep(10);
}


// Delete PID in end.
unlink("$basepath/appbuilder.pid");


// FUNCTIONS

function db_connect()
{
// MySQL Connect
    //$server_con = mysql_connect('localhost', 'appbuilder', 'aomqIPEthU2KUEcvF8qx');
    $server_con = mysqli_connect("localhost","root","Kp051108#","appbuilder");
    if (mysqli_connect_errno()) {
        return false;
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
        
    }
    //if (!$server_con || !mysql_select_db("appbuilder", $server_con)) {
        //return false;
    //}
    return $server_con;
}

