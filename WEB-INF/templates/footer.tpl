</div> {* page-content *}

{if constant('SUPPORT_LINK')}<div class="contribute-msg"><a href="{constant('SUPPORT_LINK')}" target="_blank">{if constant('SUPPORT_TEXT')}{constant('SUPPORT_TEXT')}{else}{$i18n.footer.contribute_msg}{/if}</a></div>
{else}<hr class="footer-divider">
{/if}<div class="version-and-copyright">{constant('APP_NAME')} {constant('APP_VERSION')} &middot; &copy; <a href="https://www.anuko.com" target="_blank">Anuko</a></div>

</body>
</html>
