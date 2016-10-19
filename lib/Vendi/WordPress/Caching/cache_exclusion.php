<?php

namespace Vendi\WordPress\Caching;

use Vendi\Shared\utils;

class cache_exclusion
{
/*Fields*/
    private $property;

    private $comparison;

    private $value;

    private $server_array = null;

/*Constants*/
    const PROPERTY_URL = 'url';

    const PROPERTY_USER_AGENT = 'user-agent';

    const PROPERTY_COOKIE_NAME = 'cookie-name';

    const COMPARISON_STARTS_WITH = 'starts-with';

    const COMPARISON_ENDS_WITH = 'ends-with';

    const COMPARISON_CONTAINS = 'contains';

    const COMPARISON_EXACT = 'exact';

/*Property Access*/
    public function get_property()
    {
        return $this->property;
    }

    public function set_property($property)
    {
        switch ($property)
        {
            case self::PROPERTY_URL:
            case self::PROPERTY_USER_AGENT:
            case self::PROPERTY_COOKIE_NAME:
                $this->property = $property;
                return;
        }

        throw new cache_setting_exception(__(sprintf('Unknown cache property: %1$s', $property), 'Vendi Caching'));
    }

    public function get_comparison()
    {
        return $this->comparison;
    }

    public function set_comparison($comparison)
    {
        switch ($comparison)
        {
            case self::COMPARISON_STARTS_WITH:
            case self::COMPARISON_ENDS_WITH:
            case self::COMPARISON_CONTAINS:
            case self::COMPARISON_EXACT:
                $this->comparison = $comparison;
                return;
        }

        throw new cache_setting_exception(__(sprintf('Unknown cache comparison: %1$s', $comparison), 'Vendi Caching'));
    }

    /**
     * @return string
     */
    public function get_value()
    {
        return $this->value;
    }

    public function set_value($value)
    {
        $this->value = $value;
    }

/*Constructor*/
    private function __construct($property, $comparison, $value)
    {
        $this->set_property($property);
        $this->set_comparison($comparison);
        $this->set_value($value);
    }

/*Static Factories*/
    public static function create_url_starts_with($value)
    {
        return new self(self::PROPERTY_URL, self::COMPARISON_STARTS_WITH, $value);
    }

    public static function create_url_ends_with($value)
    {
        return new self(self::PROPERTY_URL, self::COMPARISON_ENDS_WITH, $value);
    }

    public static function create_url_contains($value)
    {
        return new self(self::PROPERTY_URL, self::COMPARISON_CONTAINS, $value);
    }

    public static function create_url_exact($value)
    {
        return new self(self::PROPERTY_URL, self::COMPARISON_EXACT, $value);
    }

    public static function create_user_agent_contains($value)
    {
        return new self(self::PROPERTY_USER_AGENT, self::COMPARISON_CONTAINS, $value);
    }

    public static function create_user_agent_exact($value)
    {
        return new self(self::PROPERTY_USER_AGENT, self::COMPARISON_EXACT, $value);
    }

    public static function create_cookie_contains($value)
    {
        return new self(self::PROPERTY_COOKIE_NAME, self::COMPARISON_CONTAINS, $value);
    }

    public static function create_from_legacy($vt)
    {
        if ( ! is_array($vt))
        {
            throw new cache_setting_exception(__('Value supplied to create_from_legacy was not an array.', 'Vendi Caching'));
        }

        if ( ! array_key_exists('pt', $vt))
        {
            throw new cache_setting_exception(__('Value supplied to create_from_legacy was missing the pt key.', 'Vendi Caching'));
        }

        if ( ! array_key_exists('p', $vt))
        {
            throw new cache_setting_exception(__('Value supplied to create_from_legacy was missing the p key.', 'Vendi Caching'));
        }

        $value = $vt['p'];

        switch ($vt['pt'])
        {
            case 'eq':
                return self::create_url_exact($value);

            case 's':
                return self::create_url_starts_with($value);

            case 'e':
                return self::create_url_ends_with($value);

            case 'c':
                return self::create_url_contains($value);

            case 'uac':
                return self::create_user_agent_contains($value);

            case 'uaeq':
                return self::create_user_agent_exact($value);

            case 'cc':
                return self::create_cookie_contains($value);
        }

        throw new cache_setting_exception(__('Legacy supplied to create_from_legacy was unknown.', 'Vendi Caching'));
    }

    public function process_rule_exact($value_to_check_against)
    {
        return strtolower($value_to_check_against) == strtolower($this->get_value());
    }

    public function process_rule_starts_with($value_to_check_against)
    {
        return 0 === stripos($this->get_value(), $value_to_check_against);
    }

    public function process_rule_ends_with($value_to_check_against)
    {
        return stripos($this->get_value(), $value_to_check_against) === (strlen($this->get_value()) - strlen($value_to_check_against));
    }

    public function process_rule_contains($value_to_check_against)
    {
        return false !== stripos($this->get_value(), $value_to_check_against);
    }

/*Public Methods*/
    public function process_rule($value_to_check_against = null)
    {
        switch ($this->get_property())
        {
            case self::PROPERTY_URL:

                if (null === $value_to_check_against)
                {
                    $value_to_check_against = utils::get_server_value('REQUEST_URI');
                }

                switch ($this->get_comparison())
                {
                    case self::COMPARISON_EXACT:
                        return $this->process_rule_exact($value_to_check_against);

                    case self::COMPARISON_STARTS_WITH:
                        return $this->process_rule_starts_with($value_to_check_against);

                    case self::COMPARISON_ENDS_WITH:
                        return $this->process_rule_ends_with($value_to_check_against);

                    case self::COMPARISON_CONTAINS:
                        return $this->process_rule_contains($value_to_check_against);

                    default:
                        throw new cache_setting_exception(__(sprintf('Unsupported comparison encountered while processing URL rule: %1$s', $this->get_comparison()), 'Vendi Caching'));
                }

            case self::PROPERTY_USER_AGENT:

                if (null === $value_to_check_against)
                {
                    $value_to_check_against = utils::get_server_value('HTTP_USER_AGENT');
                }

                switch ($this->get_comparison())
                {
                    case self::COMPARISON_CONTAINS:
                        return $this->process_rule_contains($value_to_check_against);

                    case self::COMPARISON_EXACT:
                        return $this->process_rule_exact($value_to_check_against);

                    default:
                        throw new cache_setting_exception(__(sprintf('Unsupported comparison encountered while processing user agent rule: %1$s', $this->get_comparison()), 'Vendi Caching'));
                }

            case self::PROPERTY_COOKIE_NAME:

                if (null === $value_to_check_against)
                {
                    $value_to_check_against = utils::get_request_object('COOKIE');
                }

                if ( ! $value_to_check_against || ! is_array($value_to_check_against))
                {
                    return false;
                }

                switch ($this->get_comparison())
                {
                    case self::COMPARISON_CONTAINS:
                        foreach ($value_to_check_against as $cookie_name => $value)
                        {
                            if ($this->process_rule_contains($cookie_name))
                            {
                                return true;
                            }
                        }
                        return false;

                    default:
                        throw new cache_setting_exception(__(sprintf('Unsupported comparison encountered while processing cookie rule: %1$s', $this->get_comparison()), 'Vendi Caching'));
                }

            default:
                throw new cache_setting_exception(__(sprintf('Unknown property encountered while processing rule: %1$s', $this->get_property()), 'Vendi Caching'));
        }
    }
}
