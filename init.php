<?php
class Email_Forwarder extends Plugin {

    /** @var PluginHost $host */
    private $host;

    function about() {
        return array(null,
            "Forward feed updates to user one by one or in digest",
            "hardway");
    }

    function init($host)
    {
        $this->host = $host;

        $host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
        $host->add_hook($host::HOOK_PREFS_EDIT_FEED, $this);
        $host->add_hook($host::HOOK_PREFS_SAVE_FEED, $this);
    }

    function get_js() {
        // return file_get_contents(__DIR__ . "/init.js");
    }

    function hook_prefs_edit_feed($feed_id) {
        $feeds_onebyone = $this->get_stored_array("forward_onebyone");
        ?>

        <header><?= __("Email Forwarder") ?></header>
        <section>
            <fieldset>
                <label class='checkbox'>
                    <?= \Controls\checkbox_tag("email_forward_onebyone", in_array($feed_id, $feeds_onebyone)) ?>
                    <?= __('Forward One by One') ?>
                </label>
            </fieldset>
        </section>
        <?php
    }

    function hook_prefs_save_feed($feed_id) {
        $feeds_onebyone = $this->get_stored_array("forward_onebyone");

        $onebyone = checkbox_to_sql_bool($_POST["email_forward_onebyone"] ?? "");

        $found = array_search($feed_id, $feeds_onebyone);

        if ($onebyone) {
            if ($found === false) {
                array_push($feeds_onebyone, $feed_id);
            }
        } else {
            if ($found !== false) {
                unset($feeds_onebyone[$found]);
            }
        }

        $this->host->set($this, "forward_onebyone", $feeds_onebyone);
    }

    /**
     * Send article content via Email
     */
    function process_article(array $article) : array {
        Debug::log("Email Forward Article: ".$article['title']);
        $owner = ORM::for_table('ttrss_users')->find_one($article['owner_uid']);

        if($owner->email){
            Debug::log("Sending email to $owner->email");

            $mailer = new Mailer();

            $rc = $mailer->mail(["to_name" => $owner->full_name,
                "to_address" => $owner->email,
                "subject" => "[TTRSS-EMAIL-FORWARDER] ".$article['title'],
                "message" => $article["content"],
                "message_html" => $article["content"],
                'headers'=>["MIME-Version: 1.0", "Content-Type: text/html; charset=UTF-8"],
            ]);

            if($mailer->error()){
                Debug::log("Email Error($rc): ".$mailer->error());
            }
        }

        return $article;
    }

    /**
     * @param string $name
     * @return array<int|string, mixed>
     * @throws PDOException
     * @deprecated
     */
    private function get_stored_array(string $name) : array {
        return $this->host->get_array($this, $name);
    }

    function hook_article_filter($article) {
        $feeds_onebyone = $this->get_stored_array("forward_onebyone");
        $feed_id = $article["feed"]["id"];

        if (!in_array($feed_id, $feeds_onebyone))
            return $article;

        return $this->process_article($article);
    }


    function api_version() {
        return 2;
    }
}
