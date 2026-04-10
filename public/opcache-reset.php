<?php
// One-shot opcache reset. Delete after use.
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo 'opcache reset: ok';
} else {
    echo 'opcache not available';
}
