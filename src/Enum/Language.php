<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Enum;

enum Language: string
{
    private const string DATATABLES_VERSION = '2.3.7';

    case AF      = 'af';
    case AM      = 'am';
    case AR      = 'ar';
    case AZ_AZ   = 'az_AZ';
    case BE      = 'be';
    case BG      = 'bg';
    case BN      = 'bn';
    case BS_BA   = 'bs_BA';
    case CA      = 'ca';
    case CO      = 'co';
    case CS      = 'cs';
    case CY      = 'cy';
    case DA      = 'da';
    case DE      = 'de-DE';
    case DE_DE   = 'de_DE';
    case EL      = 'el';
    case EN      = 'en-GB';
    case EN_GB   = 'en_GB';
    case EO      = 'eo';
    case ES      = 'es-ES';
    case ES_AR   = 'es_AR';
    case ES_CL   = 'es_CL';
    case ES_CO   = 'es_CO';
    case ES_ES   = 'es_ES';
    case ES_MX   = 'es_MX';
    case ET      = 'et';
    case EU      = 'eu';
    case FA      = 'fa';
    case FI      = 'fi';
    case FIL     = 'fil';
    case FR      = 'fr-FR';
    case FR_FR   = 'fr_FR';
    case GA      = 'ga';
    case GANDA   = 'Ganda';
    case GL      = 'gl';
    case GU      = 'gu';
    case HE      = 'he';
    case HI      = 'hi';
    case HR      = 'hr';
    case HU      = 'hu';
    case HY      = 'hy';
    case ID      = 'id';
    case ID_ALT  = 'id_ALT';
    case IS      = 'is';
    case IT_IT   = 'it_IT';
    case JA      = 'ja';
    case JV      = 'jv';
    case KA      = 'ka';
    case KK      = 'kk';
    case KM      = 'km';
    case KN      = 'kn';
    case KO      = 'ko';
    case KU      = 'ku';
    case KY      = 'ky';
    case LO      = 'lo';
    case LT      = 'lt';
    case LV      = 'lv';
    case MK      = 'mk';
    case MN      = 'mn';
    case MR      = 'mr';
    case MS      = 'ms';
    case NE      = 'ne';
    case NL_NL   = 'nl_NL';
    case NO_NB   = 'no_NB';
    case NO_NO   = 'no_NO';
    case PA      = 'pa';
    case PL      = 'pl';
    case PS      = 'ps';
    case PT_BR   = 'pt_BR';
    case PT_PT   = 'pt_PT';
    case RM      = 'rm';
    case RO      = 'ro';
    case RU      = 'ru';
    case SI      = 'si';
    case SK      = 'sk';
    case SL      = 'sl';
    case SND     = 'snd';
    case SQ      = 'sq';
    case SR      = 'sr';
    case SR_SP   = 'sr_SP';
    case SV_SE   = 'sv_SE';
    case SW      = 'sw';
    case TA      = 'ta';
    case TE      = 'te';
    case TG      = 'tg';
    case TH      = 'th';
    case TK      = 'tk';
    case TR      = 'tr';
    case UG      = 'ug';
    case UK      = 'uk';
    case UR      = 'ur';
    case UZ      = 'uz';
    case UZ_CR   = 'uz_CR';
    case VI      = 'vi';
    case ZH      = 'zh';
    case ZH_HANT = 'zh_HANT';

    public function getUrl(): string
    {
        return \sprintf('https://cdn.datatables.net/plug-ins/%s/i18n/%s.json', self::DATATABLES_VERSION, $this->value);
    }
}
