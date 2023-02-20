<?php
/**
*  @author    jaroslav nalezny <jaroslav@nalezny.cz>
*  @copyright 2007-2021 consentmanager.net
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Consentmanager extends Module
{
    protected $config_form = false;
    protected $theme_dir = _PS_THEME_DIR_;
    public $error = '';
    public $confirmation = '';

    public function __construct()
    {
        $this->name = 'consentmanager';
        $this->tab = 'front_office_features';
        $this->version = '1.0.2';
        $this->author = 'consentmanager.net';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Consent Management Provider (CMP)');
        $this->description = $this->l('Become GDPR & CCPA-compliant with our Consent Management Provider (CMP) software for websites. Ask your website visitors for consent for GDPR/Cookies. The Cookie-Crawler will detect all Cookies on your website. More than 35 Languages, Your own Logo/Design, many Reports, integrated A/B-Testing. Try now for free!');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        Configuration::updateValue('CONSENTMANAGER_ENABLE', false);
        Configuration::updateValue('CONSENTMANAGER_SETUP', 1);
        Configuration::updateValue('CONSENTMANAGER_ID', '');
        Configuration::updateValue('CONSENTMANAGER_ADDITIONAL', '');
        

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader');
    }

    public function uninstall()
    {
        $this->code('remove');

        Configuration::deleteByName('CONSENTMANAGER_ENABLE');
        Configuration::deleteByName('CONSENTMANAGER_SETUP');
        Configuration::deleteByName('CONSENTMANAGER_ID');
        Configuration::deleteByName('CONSENTMANAGER_ADDITIONAL');
        
        return parent::uninstall();
    }

    public function getContent()
    {

        if (((bool)Tools::isSubmit('submitConsentmanagerModule')) == true) {
            $this->postProcess();
        }

        $return = '';

        if (!empty($this->confirmation)) {
            $return .= $this->displayConfirmation($this->confirmation);
        }

        if (!empty($this->error)) {
            $return .= $this->displayError($this->error);
        }

        return $return.$this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitConsentmanagerModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable'),
                        'name' => 'CONSENTMANAGER_ENABLE',
                        'is_bool' => true,
                        'desc' => $this->l('Enable consentmanager.net'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'CONSENTMANAGER_ID',
                        'label' => $this->l('CMP Code-ID'),
                        'desc' => $this->l('You can find your CMP Code-ID in consentmanager.net client area CMP => Get Code.'),
                    ),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Options'),
                        'name' => 'CONSENTMANAGER_SETUP',
                        'values' => array (
                            array(
                                'id' => 'semi_automatic_block',
                                'value' => 1,
                                'label' => $this->l('Semi-Automatic blocking'),
                            ),
                            array(
                                'id' => 'automatic_block',
                                'value' => 2,
                                'label' => $this->l('Automatic blocking'),
                            ),
                        ),
                        'desc' => $this->l('Select an option how to implement consentmanager.net script'),
                    ),
                    array(
                        'type' => 'textarea',
                        'name' => 'CONSENTMANAGER_ADDITIONAL',
                        'label' => $this->l('Additional JavaScript'),
                        'desc' => $this->l('Additional JavaScript code will be added before main consentmanager code. Do not include <script>. The wrapper will be added automatically!'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'CONSENTMANAGER_ENABLE' => Configuration::get('CONSENTMANAGER_ENABLE'),
            'CONSENTMANAGER_ID' => Configuration::get('CONSENTMANAGER_ID'),
            'CONSENTMANAGER_SETUP' => Configuration::get('CONSENTMANAGER_SETUP'),
            'CONSENTMANAGER_ADDITIONAL' => Configuration::get('CONSENTMANAGER_ADDITIONAL'),
        );
    }

    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        $this->code();

    }

    protected function code($purpose = 'add')
    {
        $ps_version = $this->psVersion();

        $id = Configuration::get('CONSENTMANAGER_ID');
        $setup = Configuration::get('CONSENTMANAGER_SETUP');
        $enable = Configuration::get('CONSENTMANAGER_ENABLE');
        $additional = Configuration::get('CONSENTMANAGER_ADDITIONAL');

        $automatic_blocking_script_2 = '<!--consentmanager_code-->{literal}<script>'.$additional.'</script><script type="text/javascript" data-cmp-ab="1" src="https://cdn.consentmanager.net/delivery/autoblocking/'.$id.'.js" data-cmp-host="delivery.consentmanager.net" data-cmp-cdn="cdn.consentmanager.net" data-cmp-codesrc="6"></script>{/literal}<!--consentmanager_code-->';

        $automatic_blocking_script_1 = '<!--consentmanager_code-->{literal}<script>window.gdprAppliesGlobally=true;if(!("cmp_id" in window)||window.cmp_id<1){window.cmp_id=0}if(!("cmp_cdid" in window)){window.cmp_cdid="'.$id.'"}if(!("cmp_params" in window)){window.cmp_params=""}if(!("cmp_host" in window)){window.cmp_host="delivery.consentmanager.net"}if(!("cmp_cdn" in window)){window.cmp_cdn="cdn.consentmanager.net"}if(!("cmp_proto" in window)){window.cmp_proto="https:"}if(!("cmp_codesrc" in window)){window.cmp_codesrc="6"}window.cmp_getsupportedLangs=function(){var b=["DE","EN","FR","IT","NO","DA","FI","ES","PT","RO","BG","ET","EL","GA","HR","LV","LT","MT","NL","PL","SV","SK","SL","CS","HU","RU","SR","ZH","TR","UK","AR","BS"];if("cmp_customlanguages" in window){for(var a=0;a<window.cmp_customlanguages.length;a++){b.push(window.cmp_customlanguages[a].l.toUpperCase())}}return b};window.cmp_getRTLLangs=function(){return["AR"]};window.cmp_getlang=function(j){if(typeof(j)!="boolean"){j=true}if(j&&typeof(cmp_getlang.usedlang)=="string"&&cmp_getlang.usedlang!==""){return cmp_getlang.usedlang}var g=window.cmp_getsupportedLangs();var c=[];var f=location.hash;var e=location.search;var a="languages" in navigator?navigator.languages:[];if(f.indexOf("cmplang=")!=-1){c.push(f.substr(f.indexOf("cmplang=")+8,2).toUpperCase())}else{if(e.indexOf("cmplang=")!=-1){c.push(e.substr(e.indexOf("cmplang=")+8,2).toUpperCase())}else{if("cmp_setlang" in window&&window.cmp_setlang!=""){c.push(window.cmp_setlang.toUpperCase())}else{if(a.length>0){for(var d=0;d<a.length;d++){c.push(a[d])}}}}}if("language" in navigator){c.push(navigator.language)}if("userLanguage" in navigator){c.push(navigator.userLanguage)}var h="";for(var d=0;d<c.length;d++){var b=c[d].toUpperCase();if(g.indexOf(b)!=-1){h=b;break}if(b.indexOf("-")!=-1){b=b.substr(0,2)}if(g.indexOf(b)!=-1){h=b;break}}if(h==""&&typeof(cmp_getlang.defaultlang)=="string"&&cmp_getlang.defaultlang!==""){return cmp_getlang.defaultlang}else{if(h==""){h="EN"}}h=h.toUpperCase();return h};(function(){var n=document;var p=window;var f="";var b="_en";if("cmp_getlang" in p){f=p.cmp_getlang().toLowerCase();if("cmp_customlanguages" in p){for(var h=0;h<p.cmp_customlanguages.length;h++){if(p.cmp_customlanguages[h].l.toUpperCase()==f.toUpperCase()){f="en";break}}}b="_"+f}function g(e,d){var l="";e+="=";var i=e.length;if(location.hash.indexOf(e)!=-1){l=location.hash.substr(location.hash.indexOf(e)+i,9999)}else{if(location.search.indexOf(e)!=-1){l=location.search.substr(location.search.indexOf(e)+i,9999)}else{return d}}if(l.indexOf("&")!=-1){l=l.substr(0,l.indexOf("&"))}return l}var j=("cmp_proto" in p)?p.cmp_proto:"https:";var o=["cmp_id","cmp_params","cmp_host","cmp_cdn","cmp_proto"];for(var h=0;h<o.length;h++){if(g(o[h],"%%%")!="%%%"){window[o[h]]=g(o[h],"")}}var k=("cmp_ref" in p)?p.cmp_ref:location.href;var q=n.createElement("script");q.setAttribute("data-cmp-ab","1");var c=g("cmpdesign","");var a=g("cmpregulationkey","");q.src=j+"//"+p.cmp_host+"/delivery/cmp.php?"+("cmp_id" in p&&p.cmp_id>0?"id="+p.cmp_id:"")+("cmp_cdid" in p?"cdid="+p.cmp_cdid:"")+"&h="+encodeURIComponent(k)+(c!=""?"&cmpdesign="+encodeURIComponent(c):"")+(a!=""?"&cmpregulationkey="+encodeURIComponent(a):"")+("cmp_params" in p?"&"+p.cmp_params:"")+(n.cookie.length>0?"&__cmpfcc=1":"")+"&l="+f.toLowerCase()+"&o="+(new Date()).getTime();q.type="text/javascript";q.async=true;if(n.currentScript){n.currentScript.parentElement.appendChild(q)}else{if(n.body){n.body.appendChild(q)}else{var m=n.getElementsByTagName("body");if(m.length==0){m=n.getElementsByTagName("div")}if(m.length==0){m=n.getElementsByTagName("span")}if(m.length==0){m=n.getElementsByTagName("ins")}if(m.length==0){m=n.getElementsByTagName("script")}if(m.length==0){m=n.getElementsByTagName("head")}if(m.length>0){m[0].appendChild(q)}}}var q=n.createElement("script");q.src=j+"//"+p.cmp_cdn+"/delivery/js/cmp"+b+".min.js";q.type="text/javascript";q.setAttribute("data-cmp-ab","1");q.async=true;if(n.currentScript){n.currentScript.parentElement.appendChild(q)}else{if(n.body){n.body.appendChild(q)}else{var m=n.getElementsByTagName("body");if(m.length==0){m=n.getElementsByTagName("div")}if(m.length==0){m=n.getElementsByTagName("span")}if(m.length==0){m=n.getElementsByTagName("ins")}if(m.length==0){m=n.getElementsByTagName("script")}if(m.length==0){m=n.getElementsByTagName("head")}if(m.length>0){m[0].appendChild(q)}}}})();window.cmp_addFrame=function(b){if(!window.frames[b]){if(document.body){var a=document.createElement("iframe");a.style.cssText="display:none";a.name=b;document.body.appendChild(a)}else{window.setTimeout(window.cmp_addFrame,10,b)}}};window.cmp_rc=function(h){var b=document.cookie;var f="";var d=0;while(b!=""&&d<100){d++;while(b.substr(0,1)==" "){b=b.substr(1,b.length)}var g=b.substring(0,b.indexOf("="));if(b.indexOf(";")!=-1){var c=b.substring(b.indexOf("=")+1,b.indexOf(";"))}else{var c=b.substr(b.indexOf("=")+1,b.length)}if(h==g){f=c}var e=b.indexOf(";")+1;if(e==0){e=b.length}b=b.substring(e,b.length)}return(f)};window.cmp_stub=function(){var a=arguments;__cmapi.a=__cmapi.a||[];if(!a.length){return __cmapi.a}else{if(a[0]==="ping"){if(a[1]===2){a[2]({gdprApplies:gdprAppliesGlobally,cmpLoaded:false,cmpStatus:"stub",displayStatus:"hidden",apiVersion:"2.0",cmpId:31},true)}else{a[2](false,true)}}else{if(a[0]==="getUSPData"){a[2]({version:1,uspString:window.cmp_rc("")},true)}else{if(a[0]==="getTCData"){__cmapi.a.push([].slice.apply(a))}else{if(a[0]==="addEventListener"||a[0]==="removeEventListener"){__cmapi.a.push([].slice.apply(a))}else{if(a.length==4&&a[3]===false){a[2]({},false)}else{__cmapi.a.push([].slice.apply(a))}}}}}}};window.cmp_msghandler=function(d){var a=typeof d.data==="string";try{var c=a?JSON.parse(d.data):d.data}catch(f){var c=null}if(typeof(c)==="object"&&c!==null&&"__cmpCall" in c){var b=c.__cmpCall;window.__cmp(b.command,b.parameter,function(h,g){var e={__cmpReturn:{returnValue:h,success:g,callId:b.callId}};d.source.postMessage(a?JSON.stringify(e):e,"*")})}if(typeof(c)==="object"&&c!==null&&"__cmapiCall" in c){var b=c.__cmapiCall;window.__cmapi(b.command,b.parameter,function(h,g){var e={__cmapiReturn:{returnValue:h,success:g,callId:b.callId}};d.source.postMessage(a?JSON.stringify(e):e,"*")})}if(typeof(c)==="object"&&c!==null&&"__uspapiCall" in c){var b=c.__uspapiCall;window.__uspapi(b.command,b.version,function(h,g){var e={__uspapiReturn:{returnValue:h,success:g,callId:b.callId}};d.source.postMessage(a?JSON.stringify(e):e,"*")})}if(typeof(c)==="object"&&c!==null&&"__tcfapiCall" in c){var b=c.__tcfapiCall;window.__tcfapi(b.command,b.version,function(h,g){var e={__tcfapiReturn:{returnValue:h,success:g,callId:b.callId}};d.source.postMessage(a?JSON.stringify(e):e,"*")},b.parameter)}};window.cmp_setStub=function(a){if(!(a in window)||(typeof(window[a])!=="function"&&typeof(window[a])!=="object"&&(typeof(window[a])==="undefined"||window[a]!==null))){window[a]=window.cmp_stub;window[a].msgHandler=window.cmp_msghandler;window.addEventListener("message",window.cmp_msghandler,false)}};window.cmp_addFrame("__cmapiLocator");window.cmp_addFrame("__cmpLocator");window.cmp_addFrame("__uspapiLocator");window.cmp_addFrame("__tcfapiLocator");window.cmp_setStub("__cmapi");window.cmp_setStub("__cmp");window.cmp_setStub("__tcfapi");window.cmp_setStub("__uspapi");</script>{/literal}<!--consentmanager_code-->';

        if ($enable == 1 && !empty($id)) {
            $this->removeCode($automatic_blocking_script_1, $automatic_blocking_script_2);
            if ($ps_version == '1.6' ) {
                
                $head_tpl_path = $this->theme_dir.'header.tpl';
                
                if (file_exists($head_tpl_path)) {
                    if ($setup == 1) {

                        //Semi-Automatic blocking (recommended)

                        $update_tpl = file_get_contents($head_tpl_path);
                        $updated_tpl = preg_replace('/(<body?.+>)/','$1'.$automatic_blocking_script_1, $update_tpl, 1);

                        $this->updateTheme($head_tpl_path, $updated_tpl);

                    } else if ($setup == 2) {
                        
                        //Automatic blocking (beta)

                        $update_tpl = file_get_contents($head_tpl_path);
                        $updated_tpl = preg_replace('/(<head?.+>)/','$1'.$automatic_blocking_script_2, $update_tpl, 1);

                        $this->updateTheme($head_tpl_path, $updated_tpl);
                    }
                }
            }

            if ($ps_version == '1.7') {
                
                $head_tpl_path = $this->theme_dir.'templates/layouts/layout-both-columns.tpl';

                if (file_exists($head_tpl_path)) {
                    
                    if ($setup == 1) {

                        //Semi-Automatic blocking (recommended)

                        $update_tpl = file_get_contents($head_tpl_path);
                        $updated_tpl = preg_replace('/(<body?.+>)/','$1'.$automatic_blocking_script_1, $update_tpl, 1);

                        $this->updateTheme($head_tpl_path, $updated_tpl);

                    } else if ($setup == 2) {
                        //Automatic blocking (beta)

                        $update_tpl = file_get_contents($head_tpl_path);
                        $updated_tpl = preg_replace('/(<head?.+>)/','$1'.$automatic_blocking_script_2, $update_tpl, 1);

                        $this->updateTheme($head_tpl_path, $updated_tpl);

                    }

                } else {
                    $this->error = '<strong>'.$head_tpl_path.'</strong> '.$this->l(' file is missing in your theme directory. If you are using child theme please copy this file from original theme to the child theme directory.');
                }
            }

        } else {
            $this->removeCode($automatic_blocking_script_1, $automatic_blocking_script_2);
        }

        if ($purpose == 'remove') {
            $this->removeCode($automatic_blocking_script_1, $automatic_blocking_script_2);   
        }
    }

    public function updateTheme($head_tpl_path, $updated_tpl)
    {
        if (file_put_contents($head_tpl_path, $updated_tpl) !== false) {
            $this->confirmation = $this->l('Module was updated correctly.');
        } else {
            $this->error = $this->l('There was an error. Please contact us on support@consentmanager.net');
        }
    }

    protected function removeCode($automatic_blocking_script_1, $automatic_blocking_script_2)
    {
        $ps_version = $this->psVersion();

        if ($ps_version == '1.6') {
            $head_tpl = 'header.tpl';
        } else if ($ps_version == '1.7') {
            $head_tpl = 'templates/layouts/layout-both-columns.tpl';
        }

        $update_tpl = file_get_contents($this->theme_dir.$head_tpl);
        $updated_tpl = preg_replace('/(<!--consentmanager_code-->?.+<!--consentmanager_code-->)/', '', $update_tpl, 1);
        file_put_contents($this->theme_dir.$head_tpl, $updated_tpl);

        $update_tpl = file_get_contents($this->theme_dir.$head_tpl);
        $updated_tpl = preg_replace('/(<!--consentmanager_code-->?.+<!--consentmanager_code-->)/', '', $update_tpl, 1);
        file_put_contents($this->theme_dir.$head_tpl, $updated_tpl);
    }

    protected function psVersion()
    {
        if (version_compare(_PS_VERSION_, '1.7', '<') === true) {
           return '1.6';
        }
        if (version_compare(_PS_VERSION_, '1.7', '>=') === true) {
           return '1.7';
        }
    }
}
