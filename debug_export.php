<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
fwrite(STDERR,"Legacy debug exporter disabled. Use tools/check-runtime.php; no patient export performed.\n");
exit(1);
