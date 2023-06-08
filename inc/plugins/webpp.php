<?php

/**
 * WebPP
 *
 * Change the extensions of the existing profile photos to `.webp`
 * and upload the new ones as `.webp`.
 *
 * @author Medet "tedem" Erdal <hello@tedem.dev>
 */

// mybb
if (! \defined('IN_MYBB')) {
    die('(-_*) This file cannot be accessed directly.');
}

// version controls
if (version_compare(phpversion(), '7.0', '<=') || version_compare(phpversion(), '8.3', '>=')) {
    die('(T_T) PHP version is not compatible for this plugin!');
}

// constants
\define('TEDEM_WEBPP_ID', 'webpp');
\define('TEDEM_WEBPP_NAME', 'WebPP');
\define('TEDEM_WEBPP_AUTHOR', 'tedem');
\define('TEDEM_WEBPP_VERSION', '1.0.0');

// hooks
if (! \defined('IN_ADMINCP')) {
    $plugins->add_hook('usercp_do_avatar_end', 'webpp_main');
}

function webpp_info(): array
{
    $description = <<<'HTML'
<div style="margin-top: 1em;">
    It changes the extensions of the existing profile photos to <b>.webp</b> and uploads the new profile photos as <b>.webp</b>.
</div>
HTML;

    if (webpp_is_installed()) {
        $description = $description . webpp_update_previous_avatar_extensions();
    }

    if (webpp_donation_status()) {
        $description = $description . webpp_donation();
    }

    return [
        'name'          => TEDEM_WEBPP_NAME,
        'description'   => $description,
        'website'       => 'https://tedem.dev',
        'author'        => TEDEM_WEBPP_AUTHOR,
        'authorsite'    => 'https://tedem.dev',
        'version'       => TEDEM_WEBPP_VERSION,
        'codename'      => TEDEM_WEBPP_AUTHOR . '_' . TEDEM_WEBPP_ID,
        'compatibility' => '18*',
    ];
}

function webpp_install(): void
{
    global $cache;

    // add cache
    $plugins = $cache->read(TEDEM_WEBPP_AUTHOR);

    $plugins[TEDEM_WEBPP_ID] = [
        'name'     => TEDEM_WEBPP_NAME,
        'author'   => TEDEM_WEBPP_AUTHOR,
        'version'  => TEDEM_WEBPP_VERSION,
        'donation' => 1,
    ];

    $cache->update(TEDEM_WEBPP_AUTHOR, $plugins);
}

function webpp_is_installed(): bool
{
    global $cache;

    // has cache
    $plugins = $cache->read(TEDEM_WEBPP_AUTHOR);

    return isset($plugins[TEDEM_WEBPP_ID]);
}

function webpp_uninstall(): void
{
    global $db, $cache;

    // remove cache
    $plugins = $cache->read(TEDEM_WEBPP_AUTHOR);

    unset($plugins[TEDEM_WEBPP_ID]);

    $cache->update(TEDEM_WEBPP_AUTHOR, $plugins);

    if (\count($plugins) == 0) {
        $db->delete_query('datacache', "title='" . TEDEM_WEBPP_AUTHOR . "'");
    }
}

function webpp_activate(): void
{
    //
}

function webpp_deactivate(): void
{
    //
}

function webpp_main(): void
{
    global $_FILES;

    if (! empty($_FILES['avatarupload']['name'])) {
        webpp_update_avatar_extension();
    }
}

function webpp_update_previous_avatar_extensions(): string
{
    global $mybb;

    webpp_edit_previous_avatar_extensions();

    $apply_link = 'index.php?module=config-plugins&' . TEDEM_WEBPP_ID . '=edit-previous-avatar-extensions&my_post_key=' . $mybb->post_code;

    $apply_button = ' &mdash; <a href="' . $apply_link . '"><b>Apply</b></a>';

    $message = '<b>Convert Extensions:</b> Change the extension of previously uploaded avatars to <b>.webp</b>' . $apply_button;
    $note    = '<div><span style="color: darkgrey;">└</span> <b style="color: firebrick;">Note:</b> Before proceeding with this action, make sure to create backups of both the <b>./uploads/avatars</b> folder and the database.</div>';

    return '<div style="margin-top: 1em;">' . $message . '</div>' . $note;
}

function webpp_edit_previous_avatar_extensions(): void
{
    global $mybb, $db;

    if ($mybb->get_input('my_post_key') == $mybb->post_code) {
        if ($mybb->get_input('webpp') == 'edit-previous-avatar-extensions') {
            $users = $db->simple_select('users', 'uid, avatar', "avatar != ''");

            if ($db->num_rows($users) >= 1) {
                while ($user = $db->fetch_array($users)) {
                    if (get_extension(strstr($user['avatar'], '?dateline=', true)) !== 'webp') {
                        webpp_update_avatar_extension($user['uid']);
                    }
                }
            }

            flash_message('User profile photos have been successfully converted to .webp!', 'success');
            admin_redirect('index.php?module=config-plugins');
        }
    }
}

function webpp_update_avatar_extension(int $uid = 0): void
{
    global $mybb, $db;

    // If user ID does not valid, define current user ID
    if (! $uid) {
        $uid = $mybb->user['uid'];
    }

    // Get user avatar with User ID
    $user        = $db->simple_select('users', 'avatar', "uid = '" . $uid . "'");
    $user_avatar = $db->fetch_field($user, 'avatar');

    // Change avatar extension if avatar value is not empty
    if (! empty($user_avatar)) {
        // Remove dateline value from avatar
        $user_avatar = strstr($user_avatar, '?dateline=', true);

        // Update the avatar path if this is happening in the admin panel
        if (\defined('IN_ADMINCP')) {
            $user_avatar = '../' . $user_avatar;
        }

        // Get extension from user avatar
        $user_avatar_extension = get_extension($user_avatar);

        // If the avatar extension is not 'webp' and an avatar exists, update it
        if ($user_avatar_extension !== 'webp' && file_exists($user_avatar)) {
            // Define variables
            $user_avatar_webp      = str_replace($user_avatar_extension, 'webp', $user_avatar);
            $user_avatar_webp_time = $user_avatar_webp . '?dateline=' . TIME_NOW;

            if (\defined('IN_ADMINCP')) {
                $user_avatar_webp_time = str_replace('../', '', $user_avatar_webp_time);
            }

            // Update database
            $db->update_query('users', ['avatar' => $user_avatar_webp_time], "uid = '" . $uid . "'");

            // Update avatar file
            $avatar = imagecreatefromstring(file_get_contents($user_avatar));

            if (imageistruecolor($avatar)) {
                imagewebp($avatar, $user_avatar_webp);
                imagedestroy($avatar);
            } else {
                $avatar_true_color = imagecreatetruecolor(imagesx($avatar), imagesy($avatar));

                imagecopy($avatar_true_color, $avatar, 0, 0, 0, 0, imagesx($avatar), imagesy($avatar));
                imagedestroy($avatar);

                imagewebp($avatar_true_color, $user_avatar_webp);
                imagedestroy($avatar_true_color);
            }

            // Remove old avatar
            unlink($user_avatar);
        }
    }
}

function webpp_donation(): string
{
    global $mybb;

    webpp_donation_edit();

    $BMC  = '<a href="https://www.buymeacoffee.com/tedem"><b>Buy me a coffee</b></a>';
    $KOFI = '<a href="https://ko-fi.com/tedem"><b>KO-FI</b></a>';

    $close_link = 'index.php?module=config-plugins&' . TEDEM_WEBPP_ID . '=deactivate-donation&my_post_key=' . $mybb->post_code;

    $close_button = ' &mdash; <a href="' . $close_link . '"><b>Close Donation</b></a>';

    $message = '<b>Donation:</b> Support for new plugins, themes, etc. via ' . $BMC . ' or ' . $KOFI . $close_button;

    return '<div style="margin-top: 1em;">' . $message . '</div>';
}

function webpp_donation_status(): bool
{
    global $cache;

    $donation = $cache->read(TEDEM_WEBPP_AUTHOR);

    return isset($donation[TEDEM_WEBPP_ID]['donation']) && $donation[TEDEM_WEBPP_ID]['donation'] == 1;
}

function webpp_donation_edit(): void
{
    global $mybb;

    if ($mybb->get_input('my_post_key') == $mybb->post_code) {
        global $cache;

        $plugins = $cache->read(TEDEM_WEBPP_AUTHOR);

        if ($mybb->get_input('webpp') == 'deactivate-donation') {
            $plugins[TEDEM_WEBPP_ID]['donation'] = 0;

            $cache->update(TEDEM_WEBPP_AUTHOR, $plugins);

            flash_message('The donation message has been successfully closed.', 'success');
            admin_redirect('index.php?module=config-plugins');
        }
    }
}
