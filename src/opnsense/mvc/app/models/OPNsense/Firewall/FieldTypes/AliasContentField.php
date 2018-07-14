<?php
/**
 *    Copyright (C) 2018 Deciso B.V.
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */
namespace OPNsense\Firewall\FieldTypes;

use OPNsense\Base\FieldTypes\BaseField;
use OPNsense\Base\Validators\CallbackValidator;
use Phalcon\Validation\Validator\Regex;
use Phalcon\Validation\Validator\ExclusionIn;
use Phalcon\Validation\Message;
use OPNsense\Firewall\Util;

/**
 * Class AliasContentField
 * @package OPNsense\Base\FieldTypes
 */
class AliasContentField extends BaseField
{
    /**
     * @var bool marks if this is a data node or a container
     */
    protected $internalIsContainer = false;

    /**
     * @var string default validation message string
     */
    protected $internalValidationMessage = "alias name required";

    /**
     * @var array list of known countries
     */
    private static $internalCountryCodes = array();

    /**
     * item separator
     * @var string
     */
    private $separatorchar = "\n";

    /**
     * retrieve data as options
     * @return array
     */
    public function getNodeData()
    {
        $result = array ();
        $selectlist = explode($this->separatorchar, (string)$this);
        foreach ($selectlist as $optKey) {
            $result[$optKey] = array("value"=>$optKey, "selected" => 1);
        }
        return $result;
    }


    /**
     * split and yield items
     * @param array $data to validate
     * @return \Generator
     */
    private function getItems($data)
    {
        foreach (explode($this->separatorchar, $data) as $value) {
            yield $value;
        }
    }

    /**
     * fetch valid country codes
     * @return array valid country codes
     */
    private function getCountryCodes()
    {
        if (empty(self::$internalCountryCodes)) {
            foreach (explode("\n", file_get_contents('/usr/local/opnsense/contrib/tzdata/iso3166.tab')) as $line) {
                $line = trim($line);
                if (strlen($line) > 3 && substr($line, 0, 1) != '#') {
                    self::$internalCountryCodes[] = substr($line, 0, 2);
                }
            }
        }
        return self::$internalCountryCodes;
    }

    /**
     * Validate port alias options
     * @param array $data to validate
     * @return bool|Callback
     */
    private function validatePort($data)
    {
        $messages = array();
        foreach ($this->getItems($data) as $port) {
            if (!Util::isAlias($port) && !Util::isPort($port, true)) {
                $messages[] = sprintf(
                    gettext('Entry "%s" is not a valid port number.'),
                    $port
                );
            }
        }
        return $messages;
    }

    /**
     * Validate host options
     * @param array $data to validate
     * @return bool|Callback
     */
    private function validateHost($data)
    {
        $messages = array();
        foreach ($this->getItems($data) as $host) {
            if (!Util::isAlias($host) && !Util::isIpAddress($host) && !Util::isDomain($host)) {
                $messages[] = sprintf(
                    gettext('Entry "%s" is not a valid hostname or IP address.'),
                    $host
                );
            }
        }
        return $messages;
    }

    /**
     * Validate network options
     * @param array $data to validate
     * @return bool|Callback
     */
    private function validateNetwork($data)
    {
        $messages = array();
        foreach ($this->getItems($data) as $network) {
            $ipaddr_count = 0;
            $domain_alias_count = 0;
            foreach (explode('-', $network) as $tmpaddr) {
                if (Util::isIpAddress($tmpaddr)) {
                    $ipaddr_count++;
                } elseif (trim($tmpaddr) != "") {
                    $domain_alias_count++;
                }
            }
            if (!Util::isAlias($network) && !Util::isIpAddress($network) && !Util::isSubnet($network) &&
                    !($ipaddr_count == 2 && $domain_alias_count == 0)) {
                $messages[] = sprintf(
                    gettext('Entry "%s" is not a valid hostname or IP address.'),
                    $network
                );
            }
        }
        return $messages;
    }

    /**
     * Validate host options
     * @param array $data to validate
     * @return bool|Callback
     */
    private function validateCountry($data)
    {
        $country_codes = $this->getCountryCodes();
        $messages = array();
        foreach ($this->getItems($data) as $country) {
            if (!in_array($country, $country_codes)) {
                $messages[] = sprintf(gettext('Entry "%s" is not a valid country code.'), $country);
            }
        }
        return $messages;
    }

    /**
     * retrieve field validators for this field type
     * @return array
     */
    public function getValidators()
    {
        $validators = parent::getValidators();
        if ($this->internalValue != null) {
            switch ((string)$this->getParentNode()->type) {
                case "port":
                    $validators[] = new CallbackValidator(["callback" => function ($data) {
                        return $this->validatePort($data);
                    }
                    ]);
                    break;
                case "host":
                    $validators[] = new CallbackValidator(["callback" => function ($data) {
                        return $this->validateHost($data);
                    }
                    ]);
                    break;
                case "geoip":
                    $validators[] = new CallbackValidator(["callback" => function ($data) {
                        return $this->validateCountry($data);
                    }
                    ]);
                    break;
                case "network":
                    $validators[] = new CallbackValidator(["callback" => function ($data) {
                        return $this->validateNetwork($data);
                    }
                    ]);
                    break;
                default:
                    break;
            }
        }
        return $validators;
    }
}
