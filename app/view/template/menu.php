<?php
declare(strict_types=1);
global $player;
if ($player['hospital']) {
    ?>
    <a href="/hospital">Hospital ({{HOSPITAL_COUNT}})</a><br>
    <a href="/inventory">Inventory</a><br>
    <?php
} elseif ($player['jail']) {
    ?>
    <a href="/jail">Jail ({{JAIL_COUNT}})</a><br>
    <?php
} else {
    ?>
    <a href="/">Home</a><br>
    <a href="/inventory">Inventory</a><br>
    <?php
} ?>
<a href="/events" {{EVENTS_BOLD}}>Events ({{EVENTS_COUNT}})</a><br>
<a href="/mailbox" {{MAIL_BOLD}}>Mailbox ({{MAIL_COUNT}})</a><br>
<a href="/announcements" {{ANNOUNCEMENTS_BOLD}}>Announcements ({{ANNOUNCEMENTS_COUNT}})</a><br>
<?php
if ($player['jail'] && !$player['hospital']) {
    ?>
    <a href="/gym">Jail Gym</a><br>
    <a href="/hospital">Hospital ({{HOSPITAL_COUNT}})</a><br>
    <?php
} elseif (!$player['hospital']) {
    ?>
    <a href="/explore">Explore</a><br>
    <a href="/gym">Gym</a><br>
    <a href="/crimes">Crimes</a><br>
    <a href="/job">Your Job</a><br>
    <a href="/education">Local School</a><br>
    <a href="/hospital">Hospital ({{HOSPITAL_COUNT}})</a><br>
    <a href="/jail">Jail ({{JAIL_COUNT}})</a><br>
    <?php
} else {
    ?>
    <a href="/jail">Jail ({{JAIL_COUNT}})</a><br>
    <?php
} ?>
<a href="/forums">Forums</a><br>
<a href="/newspaper">Newspaper</a><br>
<a href="/search">Search</a><br>
<?php
if ($player['gang'] && !$player['jail']) {
    ?>
    <a href="/yourgang">Your Gang</a><br>
    <?php
}
if ($player['is_staff']) {
    ?>
    <hr>
    <a href="/staff">Staff Panel</a><br>
    <hr>
    <b>Staff Online:</b><br>
    {{STAFF}}
    <?php
}
if ($player['donatordays']) {
    ?>
    <hr>
    <b>Donators Only</b><br>
    <a href="/list/friends">Friends List</a><br>
    <a href="/list/enemies">Enemy List</a>
    <?php
} ?>
<hr>
<a href="/preferences">Preferences</a><br>
<a href="/report-player">Player Report</a><br>
<a href="/helptutorial">Help Tutorial</a><br>
<a href="/gamerules">Game Rules</a><br>
<a href="/profile">My Profile</a><br>
<a href="/auth/logout">Logout</a><br><br>
Time is now<br>
{{DATE}}<br>
{{TIME}}
