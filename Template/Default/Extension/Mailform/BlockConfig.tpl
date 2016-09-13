<div id="fraym-mailform-tabs">
   <ul>
       {foreach $locales as $k => $locale}
        <li{if isFirst($locales, $k)} class="active"{/if}><a href="#htmlblock-tabs-{$k}">{$locale.name}</a></li>
       {/foreach}
   </ul>
    <div>
        {foreach $locales as $k => $locale}
            <div id="htmlblock-tabs-{$k}" class="{if isFirst($locales, $k)} active{/if}">
                <div class="form-group">
                    <label for="receiver">{_('Receiver email')}</label>
                    <input type="email" class="form-control" id="receiver" name="config[{$locale.id}][email]" value="{$blockConfig[$locale.id]['email']}" placeholder="{_('Email')}" required>
                </div>
                <div class="form-group">
                    <label for="subject">{_('Receiver subject')}</label>
                    <input type="text" class="form-control" id="subject" name="config[{$locale.id}][subject]" value="{$blockConfig[$locale.id]['subject']}" placeholder="{_('Subject')}" required>
                </div>
            </div>
        {/foreach}
    </div>
</div>
<script>$('#fraym-mailform-tabs').tabs();</script>