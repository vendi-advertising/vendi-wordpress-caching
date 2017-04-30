<?php

namespace Vendi\Cache;

/**
 * TODO: This must have happened late at night and is embarrassing. These are
 * "exceptions" in that things should be cached "except" these things. These
 * should not be defined in a class that extends \Exception.
 */
class cache_setting_exception extends \Exception
{

    const URL_STARTS_WITH = 's';

    const URL_ENDS_WITH = 'e';

    const URL_CONTAINS = 'c';

    const URL_MATCHES_EXACTLY = 'eq';

    const USER_AGENT_CONTAINS = 'uac';

    const USER_AGENT_MATCHES_EXACTLY = 'uaeq';

    const COOKIE_NAME_CONTAINS = 'cc';

}
