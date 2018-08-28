<?php

                        $csspath = "/vz/appbuilder/source/55/scss/app.scss";
                        $css = file_get_contents($csspath);
                        #$css = preg_replace('/\$positive: #([0-9a-zA-Z])+;/', "\$positive: $appcolor; \n \$bar-content-bg: $appcolor;", $css);
                        $css = preg_replace('/\$orange:\s+#([0-9a-zA-Z])+;/', "\$orange: #000000;", $css);
                        file_put_contents($csspath, $css);


