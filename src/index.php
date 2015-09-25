<?php
namespace Niteoweb\WP7GHQ;
/*
 * Plugin Name: Integration between GrooveHQ and CF7
 * Description: Plugin allows you to choose contact forms that send requests directly to GrooveHQ inbox instead to email.
 * Version:     1.0.2
 * Runtime:     5.3
 * Author:      NiteoWeb Ltd.
 * Author URI:  www.niteoweb.com
 */

class CF7GHQ
{
    protected $settings_ns = 'pluginGCF7GHQ_settings';
    protected $delete_file_hook = 'CF7GHQ_delete_file';

    public function __construct()
    {
        add_action('wpcf7_before_send_mail', array(&$this, 'submitForm'));
        add_action('admin_menu', array(&$this, 'adminMenu'));
        add_action('admin_init', array(&$this, 'settingsInit'));
        add_action($this->delete_file_hook, array(&$this, 'removeUpload'), 10, 1);
        if ($this->getOption("prevent_email", false) === "1") {
            add_filter("wpcf7_skip_mail", '__return_true', 10, 2);
        }
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
        $groovehq_copy_email = $contactform->additional_setting("groovehq_copy_email");
        $groovehq_tags = $contactform->additional_setting("groovehq_tags");
        $groovehq_inbox = $contactform->additional_setting("groovehq_inbox");

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
        if (!is_null($groovehq_tags)) {
            $ticket = array_merge($ticket, array("tags" => explode(",", $groovehq_tags[0])));
        }
        if (!is_null($groovehq_inbox)) {
            $ticket["from"] = $groovehq_inbox[0];
        }
        if (!is_null($groovehq_copy_email)) {
            add_filter('wp_mail_content_type', array(&$this, "set_html_content_type"));
            wp_mail($groovehq_copy_email[0], $ticket["subject"], $ticket["body"]);
            remove_filter('wp_mail_content_type', array(&$this, "set_html_content_type"));
        }
        $res = $this->postAPI("/tickets", $ticket);
        if ($res && $this->getOption("to_pending", false)) {
            $this->setPendingTicket($res->ticket->number);
        }

    }

    /**
     * @codeCoverageIgnore
     */
    function set_html_content_type()
    {
        return 'text/html';
    }

    /**
     * @codeCoverageIgnore
     */
    function removeUpload($filename)
    {
        @unlink($filename);
    }

    /**
     * @codeCoverageIgnore
     */
    function getFileURL($filename)
    {
        if ($submission = \WPCF7_Submission::get_instance()) {
            $uploaded_files = $submission->uploaded_files();
            foreach ((array)$uploaded_files as $name => $path) {
                $fpi = pathinfo($path);
                if ($filename === $fpi["basename"] && !empty($path)) {
                    if (@is_readable($path)) {
                        $target = str_replace($fpi["filename"], uniqid("", true), $path);
                        $wpd = wp_upload_dir();
                        copy($path, sprintf("%s/%s", $wpd["path"], basename($target)));
                        wp_schedule_single_event(14 * DAY_IN_SECONDS, $this->delete_file_hook, array($target));
                        return sprintf("%s/%s", $wpd["url"], basename($target));
                    }

                }
            }
        }
        return $filename;
    }

    function getMessage($posted, $template)
    {
        $re = '/\\[([\\w *-_:\\.\\|"\']+)\\]/mi';
        $msg = $template;
        preg_match_all($re, $msg, $matches);

        foreach ($matches[0] as $i => $whole) {
            $type = explode(' ', $matches[1][$i]);
            $type = $type[0];
            $key = explode(' ', $matches[1][$i]);
            $key = $key[1];
            if ($type === "file") {
                $msg = str_replace($whole, $this->getFileURL($posted[$key]), $msg);
            } else {
                $msg = str_replace($whole, $posted[$key], $msg);
            }

        }
        $submit_re = "/\\[submit.+\\]/mi";
        return preg_replace($submit_re, "", $msg);
    }

    /**
     * @codeCoverageIgnore
     */
    private function postAPI($endpoint, $body)
    {
        $apikey = $this->getOption("apikey", false);
        if (!$apikey) {
            return false;
        }
        $body = array_filter($body);
        $request = wp_remote_request(
            sprintf('https://api.groovehq.com/v1%s', $endpoint),
            array(
                'method' => 'POST',
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
        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) > 300) {
            return false;
        }
        $response = wp_remote_retrieve_body($request);
        $response = json_decode($response);

        return $response;
    }

    /**
     * @codeCoverageIgnore
     */
    private function getAPI($endpoint)
    {
        $apikey = $this->getOption("apikey", false);
        if (!$apikey) {
            return false;
        }
        $request = wp_remote_request(
            sprintf('https://api.groovehq.com/v1%s', $endpoint),
            array(
                'method' => 'GET',
                'timeout' => 25,
                'sslverify' => false,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'CF7GHQ',
                    'Authorization' => 'Bearer ' . $this->getOption("apikey"),
                ),

            )
        );
        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) > 300) {
            return false;
        }
        $response = wp_remote_retrieve_body($request);
        $response = json_decode($response);

        return $response;
    }

    /**
     * @codeCoverageIgnore
     */
    private function setPendingTicket($ticket_id)
    {
        $apikey = $this->getOption("apikey", false);
        if (!$apikey) {
            return false;
        }
        $request = wp_remote_request(
            sprintf('https://api.groovehq.com/v1/tickets/%s/state', $ticket_id),
            array(
                'method' => 'PUT',
                'body' => json_encode(array('state' => 'pending')),
                'timeout' => 25,
                'sslverify' => false,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'CF7GHQ',
                    'Authorization' => 'Bearer ' . $this->getOption("apikey"),
                ),

            )
        );
        if (is_wp_error($request) || wp_remote_retrieve_response_code($request) > 300) {
            return false;
        }
        $response = wp_remote_retrieve_body($request);
        $response = json_decode($response);

        return $response;
    }

    /**
     * @codeCoverageIgnore
     */
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

    /**
     * @codeCoverageIgnore
     */
    public function settingsHelp()
    {
        ?>
        Plugin supports <a href="http://contactform7.com/additional-settings/" target="_blank">Additional
        Settings</a> feature of Contacts Form 7.
        You can override some settings with these tags:

        <h4>Inbox</h4>
        <pre>groovehq_inbox: my_support_inbox@domain.tld</pre>
        <p>Sets from which inbox mail will be sent to.</p>

        <h4>Copy Email</h4>
        <pre>groovehq_copy_email: backup_email@domain.tld</pre>
        <p>Copy of form submission will be sent to this email (handy if you disabled sending emails).</p>

        <h4>Tags</h4>
        <pre>groovehq_tags: support,blog</pre>
        <p>Sets tags to submitted ticket.</p>

        <?php
    }

    /**
     * @codeCoverageIgnore
     */
    public function settingsInit()
    {
        register_setting('pluginGCF7GHQ', $this->settings_ns);

        add_settings_section(
            'pluginGCF7GHQ_section',
            'Contact Form 7 settings for GrooveHQ integration',
            array(&$this, 'settingsHelp'),
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

        add_settings_field(
            'cf7ghq_to_pending',
            'Set ticket to pending after submission',
            array(&$this, 'toPendingRender'),
            'pluginGCF7GHQ',
            'pluginGCF7GHQ_section'
        );
    }

    public function getOption($opt, $default = "")
    {
        $options = get_option($this->settings_ns);
        if (array_key_exists('cf7ghq_' . $opt, $options)) {
            return $options['cf7ghq_' . $opt];
        }
        return $default;
    }

    /**
     * @codeCoverageIgnore
     */
    public function apikeyRender()
    {
        ?>
        <input type='text' name='<?php echo $this->settings_ns; ?>[cf7ghq_apikey]'
               value='<?php echo $this->getOption("apikey");
               ?>'>
        <?php

    }

    /**
     * @codeCoverageIgnore
     */
    public function toPendingRender()
    {
        ?>
        <input type='checkbox' name='<?php echo $this->settings_ns; ?>[cf7ghq_to_pending]'
            <?php checked($this->getOption("to_pending"), 1); ?> value='1'>
        <?php

    }

    /**
     * @codeCoverageIgnore
     */
    public function disableEmailRender()
    {
        ?>
        <input type='checkbox' name='<?php echo $this->settings_ns; ?>[cf7ghq_prevent_email]'
            <?php checked($this->getOption("prevent_email"), 1); ?> value='1'>
        <?php

    }

    /**
     * @codeCoverageIgnore
     */
    public function inboxRender()
    {
        $mailboxes = $this->getAPI("/mailboxes");
        if ($mailboxes) {
            ?>
            <select name='<?php echo $this->settings_ns; ?>[cf7ghq_inbox]'>
                <?php
                foreach ($mailboxes->mailboxes as $mailbox) {
                    ?>
                    <option
                        value='<?php echo $mailbox->email; ?>' <?php selected($this->getOption("inbox"), $mailbox->email); ?> ><?php echo $mailbox->name; ?></option>
                    <?php
                }
                ?>
            </select>
            <?php
        } else {
            echo "Please enter your api key";
        }
    }

    /**
     * @codeCoverageIgnore
     */
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
