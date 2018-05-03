<?php
/*
 * Target "feed" object class for returning useful info to a user
 */
require BFF_PATH . '/includes/bff-response.php';

abstract class BFFTarget extends BFFResponse {
    protected static $target = array();
}
