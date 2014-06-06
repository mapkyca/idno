<div class="navbar">
    <div class="navbar-inner">
        <ul class="nav">
            <li <?php if ($_SERVER['REQUEST_URI'] == '/account/settings/') echo 'class="active"'; ?>><a href="<?=\Idno\Core\site()->config()->url?>account/settings/" >Account settings</a></li>
            <li <?php if ($_SERVER['REQUEST_URI'] == '/account/settings/notifications/') echo 'class="active"'; ?>><a href="<?=\Idno\Core\site()->config()->url?>account/settings/notifications/" >Notifications</a></li>
            <li <?php if ($_SERVER['REQUEST_URI'] == '/account/settings/homepage/') echo 'class="active"'; ?>><a href="<?=\Idno\Core\site()->config()->url?>account/settings/homepage/" >Homepage</a></li>
            <?php /*

            This is an early development feature and is not ready to be exposed.

	        <li <?php if ($_SERVER['REQUEST_URI'] == '/account/settings/following/') echo 'class="active"'; ?>><a href="<?=\Idno\Core\site()->config()->url?>account/settings/following/" >Following</a></li>
            */ ?>
            <?=$this->draw('account/menu/items')?>
        </ul>
    </div>
</div>