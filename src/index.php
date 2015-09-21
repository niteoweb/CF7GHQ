<?php
namespace Niteoweb\WP7GHQ;

/*
 * Plugin Name: Contact Form 7 GrooveHQ integration
 * Description: Send Contact form 7 submissions to GrooveHQ.
 * Version:     1.0.0
 * Author:      NiteoWeb Ltd.
 * Author URI:  www.niteoweb.com
 */

if (version_compare(PHP_VERSION, '5.3.0', '<')) {
    ?>
    <div id="error-page">
        <p>This plugin requires PHP 5.3.0 or higher. Please contact your hosting provider about upgrading your
            server software. Your PHP version is <b><?php echo PHP_VERSION;
                ?></b></p>
    </div>
    <?php
    die();
}

class CF7GHQ
{
    protected $api_key_filed = 'pluginGCF7GHQ_settings';

    public function __construct()
    {
        add_action('wpcf7_submit', array(&$this, 'submitForm'));
        add_action('admin_menu', array(&$this, 'adminMenu'));
        add_action('admin_init', array(&$this, 'settingsInit'));
    }

    /**
     * Handler for wpcf7_submit hook.
     *
     * @param \WPCF7_ContactForm $contactform
     */
    public function submitForm($contactform)
    {
        if ($contactform->in_demo_mode()) {
            return;
        }

        $submission = \WPCF7_Submission::get_instance();
        $posted = $submission->get_posted_data();
        if (!$submission || !$posted) {
            return;
        }

        if (!isset($posted['your-email'])) {
            $sender = get_option('admin_email');
        } else {
            $sender = $posted['your-email'];
        }

        $ticket = array(
            'state' => 'pending',
            'to' => $sender,
            'subject' => sprintf('%s: %s', $contactform->title(), $sender),
            'from' => $this->getOption("inbox", "Inbox"),
            'note' => true,
            'body' => $this->getMessage($posted, $contactform->prop('form')),
        );
        $this->postAPI("/tickets", $ticket);
    }

    function getMessage($posted, $template)
    {
        $re = '/\\[([\\w *-_:\\.\\|"\']+)\\]/mi';
        $msg = $template;
        preg_match_all($re, $msg, $matches);

        foreach ($matches[0] as $i => $whole) {
            $key = explode(' ', $matches[1][$i])[1];
            $msg = str_replace($whole, $posted[$key], $msg);
        }
        $submit_re = "/\\[submit.+\\]/mi";
        return preg_replace($submit_re, "", $msg);
    }

    private function postAPI($endpoint, $body)
    {
        $apikey = $this->getOption("apikey", false);
        if(!$apikey){
            return false;
        }
        $body = array_filter($body);
        $request = wp_remote_post(
            sprintf('https://api.groovehq.com/v1%s', $endpoint),
            array(
                'body' => json_encode($body),
                'timeout' => 25,
                'sslverify' => false,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'CF7GHQ',
                    'Authorization' => 'Bearer ' . $apikey
                ),

            )
        );
        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
            return false;
        }
        $response = wp_remote_retrieve_body($request);
        $response = json_decode($response);

        return $response;
    }

    private function getAPI($endpoint)
    {
        $apikey = $this->getOption("apikey", false);
        if(!$apikey){
            return false;
        }
        $request = wp_remote_get(
            sprintf('https://api.groovehq.com/v1%s', $endpoint),
            array(
                'timeout' => 25,
                'sslverify' => false,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'CF7GHQ',
                    'Authorization' => 'Bearer ' . $this->getOption("apikey"),
                ),

            )
        );
        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) != 200) {
            return false;
        }
        $response = wp_remote_retrieve_body($request);
        $response = json_decode($response);

        return $response;
    }


    public function adminMenu()
    {
        add_options_page(
            'GrooveHQ settings for CF7',
            'CF7 GrooveHQ',
            'manage_options',
            'cf7ghq',
            array(&$this, 'optionsPage')
        );
    }

    public function settingsInit()
    {
        register_setting('pluginGCF7GHQ', 'pluginGCF7GHQ_settings');

        add_settings_section(
            'pluginGCF7GHQ_section',
            'Contact Form 7 settings for GrooveHQ integration',
            null,
            'pluginGCF7GHQ'
        );

        add_settings_field(
            'cf7ghq_apikey',
            'API key',
            array(&$this, 'apikeyRender'),
            'pluginGCF7GHQ',
            'pluginGCF7GHQ_section'
        );

        add_settings_field(
            'cf7ghq_inbox',
            'Default Inbox',
            array(&$this, 'inboxRender'),
            'pluginGCF7GHQ',
            'pluginGCF7GHQ_section'
        );

        add_settings_field(
            'cf7ghq_prevent_email',
            'Prevent sending email',
            array(&$this, 'disableEmailRender'),
            'pluginGCF7GHQ',
            'pluginGCF7GHQ_section'
        );
    }

    public function getOption($opt, $default="")
    {
        $options = get_option('pluginGCF7GHQ_settings');
        if(array_key_exists('cf7ghq_' . $opt, $options)){
            return $options['cf7ghq_' . $opt];
        }
        return $default;
    }

    public function apikeyRender()
    {
        ?>
        <input type='text' name='pluginGCF7GHQ_settings[cf7ghq_apikey]'
               value='<?php echo $this->getOption("apikey");
               ?>'>
        <?php

    }

    public function disableEmailRender()
    {
        ?>
        <input type='checkbox' name='pluginGCF7GHQ_settings[cf7ghq_prevent_email]'
            <?php checked( $this->getOption("prevent_email"), 1 ); ?> value='1'>
        <?php

    }

    public function inboxRender()
    {
        $mailboxes = $this->getAPI("/mailboxes");
        if($mailboxes) {
            ?>
            <select name='gh_settings[cf7ghq_inbox]'>
                <?php
                foreach ($mailboxes->mailboxes as $mailbox) {
                    ?>
                    <option
                        value='1' <?php selected($this->getOption("inbox"), $mailbox->name); ?> ><?php echo $mailbox->name; ?></option>
                    <?php
                }
                ?>
            </select>
            <?php
        }else{
            echo "Please enter your api key";
        }
    }

    public function optionsPage()
    {
        ?>
        <form action='options.php' method='post'>

            <?php
            settings_fields('pluginGCF7GHQ');
            do_settings_sections('pluginGCF7GHQ');
            submit_button();
            ?>

        </form>
        <?php

    }
}

// Inside WordPress
if (defined('ABSPATH')) {
    new CF7GHQ();
}
