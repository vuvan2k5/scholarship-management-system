<?php
// Security hardening: test.php debug file is disabled.
// It only printed a password hash and should not be publicly accessible.
http_response_code(404);
exit;
