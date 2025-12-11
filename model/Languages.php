<?php

namespace model;
use Config;

class Languages
{
    // we sometimes mess with this in unit tests?
    public const array SUPPORTED_LANGUAGES = array(
#   Format:
#   'code'  => 'native language name - language name in english'
        'bg' => '&#1041;&#1098;&#1083;&#1075;&#1072;&#1088;&#1089;&#1082;&#1080; - Bulgarian',
        'ca' => 'Catal&agrave; - Catalan',
        'cn' => '&#20013;&#25991; - Chinese simplified (gb2312)',
        'tw' => '&#20013;&#25991; - Chinese traditional',
        'cs' => '&#268;esky - Czech',
        'da' => 'Dansk - Danish',
        'de' => 'Deutsch - German',
        'en' => 'English',
        'es' => 'Espa&ntilde;ol - Spanish',
        'et' => 'Eesti - Estonian',
        'eu' => 'Euskara - Basque',
        'fi' => 'Suomi - Finnish',
        'fo' => 'Faroese',
        'fr' => 'Fran&ccedil;ais - French',
        'hr' => 'Hrvatski - Croatian',
        'hu' => 'Magyar - Hungarian',
        'gl' => 'Galego - Galician',
        'is' => 'Icelandic',
        'it' => 'Italiano - Italian',
        'ja' => '&#26085;&#26412;&#35486; - Japanese',
        'lt' => 'Lietuvi&#371; - Lithuanian',
        'mk' => 'Macedonian - Macedonian',
        'nl' => 'Nederlands - Dutch',
        'nb' => 'Norsk (bokm&#229;l) - Norwegian (bokm&#229;l)',
        'nn' => 'Norsk (nynorsk) - Norwegian (nynorsk)',
        'pl' => 'Polski - Polish',
        'pt-br' => 'Portugu&ecirc;s - Brazilian portuguese',
        'pt-pt' => 'Portugu&ecirc;s - European portuguese',
        'ro' => 'Limba Rom&acirc;n&#259; - Romanian',
        'ru' => '&#1056;&#1091;&#1089;&#1089;&#1082;&#1080;&#1081; - Russian',
        'sk' => 'Sloven&#269;ina - Slovak',
        'sl' => 'Sloven&scaron;&#269;ina - Slovenian',
        'sv' => 'Svenska - Swedish',
        'tr' => 'T&uuml;rk&ccedil;e - Turkish',
        'ua' => '&#1059;&#1082;&#1088;&#1072;&#1111;&#1085;&#1089;&#1100;&#1082;&#1072; - Ukrainian',
    );


    /**
     * @param bool $use_post - set to 0 if $_POST should NOT be read
     * @return string e.g en
     * Try to figure out what language the user wants based on browser / cookie
     */
    public static function check_language(bool $use_post = true): string
    {
        $supported_languages = self::SUPPORTED_LANGUAGES;

        // prefer a $_POST['lang'] if present
        if ($use_post && safepost('lang')) {
            $lang = safepost('lang');
            if (is_string($lang) && array_key_exists($lang, $supported_languages)) {
                return $lang;
            }
        }

        // Failing that, is there a $_COOKIE['lang'] ?
        if (safecookie('lang')) {
            $lang = safecookie('lang');
            if (!empty($lang) && array_key_exists($lang, $supported_languages)) {
                return $lang;
            }
        }

        $lang = Config::read_string('default_language');

        // If not, did the browser give us any hint(s)?
        if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $lang_array = preg_split('/(\s*,\s*)/', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($lang_array as $value) {
                $lang_next = strtolower(trim($value));
                $lang_next = preg_replace('/;.*$/', '', $lang_next); # remove things like ";q=0.8"
                if (array_key_exists($lang_next, $supported_languages) && is_string($lang_next)) {
                    return $lang_next;
                }
            }
        }
        return $lang;
    }
}
