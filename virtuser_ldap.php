<?php

/**
 * LDAP based User-to-Email and Email-to-User lookup
 *
 * The email query can return more than one record to create more identities.
 * This requires identities_level option to be set to value less than 2.
 *
 * @version @package_version@
 * @author Jakub Jirutka
 * @license WTFPL
 */
class virtuser_ldap extends rcube_plugin {

    private $app;
    private $ldap;
    private $debug = false;


    function init() {
        $this->app = rcmail::get_instance();
        $this->debug = $this->app->config->get('ldap_debug');

        $this->add_hook('user2email', array($this, 'user2email'));
        $this->add_hook('email2user', array($this, 'email2user'));
    }

    function user2email($params) {

        $this->log_msg('Search email for user: ' . $params['user']);

        if ($this->init_ldap()) {
            foreach ($this->ldap->search('*', $params['user'], 1)->records as $record) {

                $email = rcube_utils::idn_to_ascii(self::record_attr('email', $record));
                $this->log_msg("Found email: $email");

                if ($params['extended']) {
                    $params['email'][] = array(
                        'email'        => $email,
                        'name'         => self::record_attr('name', $record),
                        'organization' => self::record_attr('organization', $record)
                    );
                } else {
                    $params['email'][] = $email;
                }

                if ($params['first']) {
                    break;
                }
            }
        }
        if (!$params['email']) {
            $this->log_msg('No email found');
        }
        return $params;
    }

    function email2user($params) {

        $this->log_msg('Search user for email: ' . $params['email']);

        if ($this->init_ldap()) {
            $records = $this->ldap->search('*', $params['email'], 1)->records;

            if (count($records) == 1) {
                $params['user'] = $records[0]['username'];
                $this->log_msg('Found user: ' . $params['user']);

            } elseif (count($records) > 1) {
                $this->log_msg('Found more than one user entry');

            } else {
                $this->log_msg('No user found');
            }
        }
        return $params;
    }

    function init_ldap() {

        if (!$this->ldap) {
            $this->load_config();

            $this->ldap = new rcube_ldap(
                $this->app->config->get('virtuser_ldap'),
                $this->debug,
                $this->app->config->mail_domain($_SESSION['imap_host'])
            );
        }

        if (!$this->ldap->ready) {
            $this->log_msg('Failed to initialize LDAP connection!');
        }
        return $this->ldap->ready;
    }

    private function log_msg($str) {
        if ($this->debug) {
            rcube::write_log('ldap', "virtuser_ldap: $str");
        }
    }

    private static function record_attr($attr_name, $record) {
        $value = $record[$attr_name];
        return is_array($value) ? $value[0] : $value;
    }
}
