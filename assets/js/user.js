(() => {
  const state = {
    map: null,
    marker: null,
    selectedLocation: null,
    mapReady: false,
  };

  const isEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
  const isName = (value) => /^[A-Za-zÀ-ÖØ-öø-ÿ'\-\s]{2,40}$/.test(value);
  const startsWithUppercaseLetter = (value) => /^[A-ZÀ-ÖØ-Þ]/.test(String(value || '').trim());
  const normalizePhone = (value) => String(value || '').replace(/[^\d+]/g, '').replace(/(?!^)\+/g, '');
  const extractDialPrefix = (value) => {
    const normalized = normalizePhone(value);
    const match = normalized.match(/^\+\d{1,4}/);
    return match ? match[0] : '';
  };
  const isPhone = (value) => /^\+\d{8,15}$/.test(normalizePhone(value));

  const getCountryPrefixDigits = (info) => String(info?.p || '').replace(/\D/g, '');

  const stripCountryPrefix = (phoneRaw, info) => {
    const normalized = normalizePhone(phoneRaw);
    const prefixDigits = getCountryPrefixDigits(info);
    if (!normalized || !prefixDigits) return normalized.replace(/^\+/, '');

    const digitsOnly = normalized.replace(/^\+/, '');
    if (digitsOnly.startsWith(prefixDigits)) {
      return digitsOnly.slice(prefixDigits.length);
    }

    return digitsOnly;
  };

  const COUNTRY_PHONE_DATA = {
    afghanistan: { p: '+93', f: '🇦🇫', name: 'Afghanistan' },
    albania: { p: '+355', f: '🇦🇱', name: 'Albania' },
    algeria: { p: '+213', f: '🇩🇿', name: 'Algeria' },
    andorra: { p: '+376', f: '🇦🇩', name: 'Andorra' },
    angola: { p: '+244', f: '🇦🇴', name: 'Angola' },
    argentina: { p: '+54', f: '🇦🇷', name: 'Argentina' },
    armenia: { p: '+374', f: '🇦🇲', name: 'Armenia' },
    australia: { p: '+61', f: '🇦🇺', name: 'Australia' },
    austria: { p: '+43', f: '🇦🇹', name: 'Austria' },
    azerbaijan: { p: '+994', f: '🇦🇿', name: 'Azerbaijan' },
    bahamas: { p: '+1242', f: '🇧🇸', name: 'Bahamas' },
    bahrain: { p: '+973', f: '🇧🇭', name: 'Bahrain' },
    bangladesh: { p: '+880', f: '🇧🇩', name: 'Bangladesh' },
    barbados: { p: '+1246', f: '🇧🇧', name: 'Barbados' },
    belarus: { p: '+375', f: '🇧🇾', name: 'Belarus' },
    'republic of belarus': { p: '+375', f: '🇧🇾', name: 'Belarus' },
    belgium: { p: '+32', f: '🇧🇪', name: 'Belgium' },
    belize: { p: '+501', f: '🇧🇿', name: 'Belize' },
    benin: { p: '+229', f: '🇧🇯', name: 'Benin' },
    bhutan: { p: '+975', f: '🇧🇹', name: 'Bhutan' },
    bolivia: { p: '+591', f: '🇧🇴', name: 'Bolivia' },
    bosnia: { p: '+387', f: '🇧🇦', name: 'Bosnia and Herzegovina' },
    'bosnia and herzegovina': { p: '+387', f: '🇧🇦', name: 'Bosnia and Herzegovina' },
    botswana: { p: '+267', f: '🇧🇼', name: 'Botswana' },
    brazil: { p: '+55', f: '🇧🇷', name: 'Brazil' },
    brunei: { p: '+673', f: '🇧🇳', name: 'Brunei' },
    bulgaria: { p: '+359', f: '🇧🇬', name: 'Bulgaria' },
    'burkina faso': { p: '+226', f: '🇧🇫', name: 'Burkina Faso' },
    burundi: { p: '+257', f: '🇧🇮', name: 'Burundi' },
    cambodia: { p: '+855', f: '🇰🇭', name: 'Cambodia' },
    cameroon: { p: '+237', f: '🇨🇲', name: 'Cameroon' },
    canada: { p: '+1', f: '🇨🇦', name: 'Canada' },
    'cape verde': { p: '+238', f: '🇨🇻', name: 'Cape Verde' },
    'cabo verde': { p: '+238', f: '🇨🇻', name: 'Cape Verde' },
    'central african republic': { p: '+236', f: '🇨🇫', name: 'Central African Republic' },
    chad: { p: '+235', f: '🇹🇩', name: 'Chad' },
    chile: { p: '+56', f: '🇨🇱', name: 'Chile' },
    china: { p: '+86', f: '🇨🇳', name: 'China' },
    colombia: { p: '+57', f: '🇨🇴', name: 'Colombia' },
    comoros: { p: '+269', f: '🇰🇲', name: 'Comoros' },
    congo: { p: '+242', f: '🇨🇬', name: 'Congo' },
    'republic of the congo': { p: '+242', f: '🇨🇬', name: 'Congo' },
    'dr congo': { p: '+243', f: '🇨🇩', name: 'Democratic Republic of the Congo' },
    'democratic republic of the congo': { p: '+243', f: '🇨🇩', name: 'Democratic Republic of the Congo' },
    'costa rica': { p: '+506', f: '🇨🇷', name: 'Costa Rica' },
    croatia: { p: '+385', f: '🇭🇷', name: 'Croatia' },
    cuba: { p: '+53', f: '🇨🇺', name: 'Cuba' },
    cyprus: { p: '+357', f: '🇨🇾', name: 'Cyprus' },
    czechia: { p: '+420', f: '🇨🇿', name: 'Czech Republic' },
    'czech republic': { p: '+420', f: '🇨🇿', name: 'Czech Republic' },
    denmark: { p: '+45', f: '🇩🇰', name: 'Denmark' },
    djibouti: { p: '+253', f: '🇩🇯', name: 'Djibouti' },
    dominica: { p: '+1767', f: '🇩🇲', name: 'Dominica' },
    'dominican republic': { p: '+1809', f: '🇩🇴', name: 'Dominican Republic' },
    ecuador: { p: '+593', f: '🇪🇨', name: 'Ecuador' },
    egypt: { p: '+20', f: '🇪🇬', name: 'Egypt' },
    'el salvador': { p: '+503', f: '🇸🇻', name: 'El Salvador' },
    eritrea: { p: '+291', f: '🇪🇷', name: 'Eritrea' },
    estonia: { p: '+372', f: '🇪🇪', name: 'Estonia' },
    eswatini: { p: '+268', f: '🇸🇿', name: 'Eswatini' },
    ethiopia: { p: '+251', f: '🇪🇹', name: 'Ethiopia' },
    fiji: { p: '+679', f: '🇫🇯', name: 'Fiji' },
    finland: { p: '+358', f: '🇫🇮', name: 'Finland' },
    france: { p: '+33', f: '🇫🇷', name: 'France' },
    gabon: { p: '+241', f: '🇬🇦', name: 'Gabon' },
    gambia: { p: '+220', f: '🇬🇲', name: 'Gambia' },
    georgia: { p: '+995', f: '🇬🇪', name: 'Georgia' },
    germany: { p: '+49', f: '🇩🇪', name: 'Germany' },
    ghana: { p: '+233', f: '🇬🇭', name: 'Ghana' },
    greece: { p: '+30', f: '🇬🇷', name: 'Greece' },
    grenada: { p: '+1473', f: '🇬🇩', name: 'Grenada' },
    guatemala: { p: '+502', f: '🇬🇹', name: 'Guatemala' },
    guinea: { p: '+224', f: '🇬🇳', name: 'Guinea' },
    'guinea bissau': { p: '+245', f: '🇬🇼', name: 'Guinea-Bissau' },
    guyana: { p: '+592', f: '🇬🇾', name: 'Guyana' },
    haiti: { p: '+509', f: '🇭🇹', name: 'Haiti' },
    honduras: { p: '+504', f: '🇭🇳', name: 'Honduras' },
    hungary: { p: '+36', f: '🇭🇺', name: 'Hungary' },
    iceland: { p: '+354', f: '🇮🇸', name: 'Iceland' },
    india: { p: '+91', f: '🇮🇳', name: 'India' },
    indonesia: { p: '+62', f: '🇮🇩', name: 'Indonesia' },
    iran: { p: '+98', f: '🇮🇷', name: 'Iran' },
    'iran, islamic republic of': { p: '+98', f: '🇮🇷', name: 'Iran' },
    'islamic republic of iran': { p: '+98', f: '🇮🇷', name: 'Iran' },
    iraq: { p: '+964', f: '🇮🇶', name: 'Iraq' },
    ireland: { p: '+353', f: '🇮🇪', name: 'Ireland' },
    israel: { p: '+972', f: '🇮🇱', name: 'Israel' },
    italy: { p: '+39', f: '🇮🇹', name: 'Italy' },
    jamaica: { p: '+1876', f: '🇯🇲', name: 'Jamaica' },
    japan: { p: '+81', f: '🇯🇵', name: 'Japan' },
    jordan: { p: '+962', f: '🇯🇴', name: 'Jordan' },
    kazakhstan: { p: '+7', f: '🇰🇿', name: 'Kazakhstan' },
    kenya: { p: '+254', f: '🇰🇪', name: 'Kenya' },
    kuwait: { p: '+965', f: '🇰🇼', name: 'Kuwait' },
    kyrgyzstan: { p: '+996', f: '🇰🇬', name: 'Kyrgyzstan' },
    laos: { p: '+856', f: '🇱🇦', name: 'Laos' },
    latvia: { p: '+371', f: '🇱🇻', name: 'Latvia' },
    lebanon: { p: '+961', f: '🇱🇧', name: 'Lebanon' },
    lesotho: { p: '+266', f: '🇱🇸', name: 'Lesotho' },
    liberia: { p: '+231', f: '🇱🇷', name: 'Liberia' },
    libya: { p: '+218', f: '🇱🇾', name: 'Libya' },
    liechtenstein: { p: '+423', f: '🇱🇮', name: 'Liechtenstein' },
    lithuania: { p: '+370', f: '🇱🇹', name: 'Lithuania' },
    luxembourg: { p: '+352', f: '🇱🇺', name: 'Luxembourg' },
    madagascar: { p: '+261', f: '🇲🇬', name: 'Madagascar' },
    malawi: { p: '+265', f: '🇲🇼', name: 'Malawi' },
    malaysia: { p: '+60', f: '🇲🇾', name: 'Malaysia' },
    maldives: { p: '+960', f: '🇲🇻', name: 'Maldives' },
    mali: { p: '+223', f: '🇲🇱', name: 'Mali' },
    malta: { p: '+356', f: '🇲🇹', name: 'Malta' },
    mauritania: { p: '+222', f: '🇲🇷', name: 'Mauritania' },
    mauritius: { p: '+230', f: '🇲🇺', name: 'Mauritius' },
    mexico: { p: '+52', f: '🇲🇽', name: 'Mexico' },
    moldova: { p: '+373', f: '🇲🇩', name: 'Moldova' },
    monaco: { p: '+377', f: '🇲🇨', name: 'Monaco' },
    mongolia: { p: '+976', f: '🇲🇳', name: 'Mongolia' },
    montenegro: { p: '+382', f: '🇲🇪', name: 'Montenegro' },
    morocco: { p: '+212', f: '🇲🇦', name: 'Morocco' },
    mozambique: { p: '+258', f: '🇲🇿', name: 'Mozambique' },
    myanmar: { p: '+95', f: '🇲🇲', name: 'Myanmar' },
    burma: { p: '+95', f: '🇲🇲', name: 'Myanmar' },
    namibia: { p: '+264', f: '🇳🇦', name: 'Namibia' },
    nepal: { p: '+977', f: '🇳🇵', name: 'Nepal' },
    netherlands: { p: '+31', f: '🇳🇱', name: 'Netherlands' },
    'new zealand': { p: '+64', f: '🇳🇿', name: 'New Zealand' },
    nicaragua: { p: '+505', f: '🇳🇮', name: 'Nicaragua' },
    niger: { p: '+227', f: '🇳🇪', name: 'Niger' },
    nigeria: { p: '+234', f: '🇳🇬', name: 'Nigeria' },
    'north korea': { p: '+850', f: '🇰🇵', name: 'North Korea' },
    'south korea': { p: '+82', f: '🇰🇷', name: 'South Korea' },
    'korea republic of': { p: '+82', f: '🇰🇷', name: 'South Korea' },
    norway: { p: '+47', f: '🇳🇴', name: 'Norway' },
    oman: { p: '+968', f: '🇴🇲', name: 'Oman' },
    pakistan: { p: '+92', f: '🇵🇰', name: 'Pakistan' },
    panama: { p: '+507', f: '🇵🇦', name: 'Panama' },
    paraguay: { p: '+595', f: '🇵🇾', name: 'Paraguay' },
    peru: { p: '+51', f: '🇵🇪', name: 'Peru' },
    philippines: { p: '+63', f: '🇵🇭', name: 'Philippines' },
    poland: { p: '+48', f: '🇵🇱', name: 'Poland' },
    portugal: { p: '+351', f: '🇵🇹', name: 'Portugal' },
    qatar: { p: '+974', f: '🇶🇦', name: 'Qatar' },
    romania: { p: '+40', f: '🇷🇴', name: 'Romania' },
    russia: { p: '+7', f: '🇷🇺', name: 'Russia' },
    'russian federation': { p: '+7', f: '🇷🇺', name: 'Russia' },
    rwanda: { p: '+250', f: '🇷🇼', name: 'Rwanda' },
    'saudi arabia': { p: '+966', f: '🇸🇦', name: 'Saudi Arabia' },
    senegal: { p: '+221', f: '🇸🇳', name: 'Senegal' },
    serbia: { p: '+381', f: '🇷🇸', name: 'Serbia' },
    seychelles: { p: '+248', f: '🇸🇨', name: 'Seychelles' },
    'sierra leone': { p: '+232', f: '🇸🇱', name: 'Sierra Leone' },
    singapore: { p: '+65', f: '🇸🇬', name: 'Singapore' },
    slovakia: { p: '+421', f: '🇸🇰', name: 'Slovakia' },
    slovenia: { p: '+386', f: '🇸🇮', name: 'Slovenia' },
    somalia: { p: '+252', f: '🇸🇴', name: 'Somalia' },
    'south africa': { p: '+27', f: '🇿🇦', name: 'South Africa' },
    'south sudan': { p: '+211', f: '🇸🇸', name: 'South Sudan' },
    spain: { p: '+34', f: '🇪🇸', name: 'Spain' },
    'sri lanka': { p: '+94', f: '🇱🇰', name: 'Sri Lanka' },
    sudan: { p: '+249', f: '🇸🇩', name: 'Sudan' },
    suriname: { p: '+597', f: '🇸🇷', name: 'Suriname' },
    sweden: { p: '+46', f: '🇸🇪', name: 'Sweden' },
    switzerland: { p: '+41', f: '🇨🇭', name: 'Switzerland' },
    syria: { p: '+963', f: '🇸🇾', name: 'Syria' },
    taiwan: { p: '+886', f: '🇹🇼', name: 'Taiwan' },
    tajikistan: { p: '+992', f: '🇹🇯', name: 'Tajikistan' },
    tanzania: { p: '+255', f: '🇹🇿', name: 'Tanzania' },
    thailand: { p: '+66', f: '🇹🇭', name: 'Thailand' },
    togo: { p: '+228', f: '🇹🇬', name: 'Togo' },
    tonga: { p: '+676', f: '🇹🇴', name: 'Tonga' },
    'trinidad and tobago': { p: '+1868', f: '🇹🇹', name: 'Trinidad and Tobago' },
    tunisia: { p: '+216', f: '🇹🇳', name: 'Tunisia' },
    tunisie: { p: '+216', f: '🇹🇳', name: 'Tunisia' },
    turkey: { p: '+90', f: '🇹🇷', name: 'Turkey' },
    türkiye: { p: '+90', f: '🇹🇷', name: 'Turkey' },
    turkiye: { p: '+90', f: '🇹🇷', name: 'Turkey' },
    turkmenistan: { p: '+993', f: '🇹🇲', name: 'Turkmenistan' },
    uganda: { p: '+256', f: '🇺🇬', name: 'Uganda' },
    ukraine: { p: '+380', f: '🇺🇦', name: 'Ukraine' },
    'united arab emirates': { p: '+971', f: '🇦🇪', name: 'United Arab Emirates' },
    uae: { p: '+971', f: '🇦🇪', name: 'United Arab Emirates' },
    'united kingdom': { p: '+44', f: '🇬🇧', name: 'United Kingdom' },
    uk: { p: '+44', f: '🇬🇧', name: 'United Kingdom' },
    england: { p: '+44', f: '🇬🇧', name: 'United Kingdom' },
    britain: { p: '+44', f: '🇬🇧', name: 'United Kingdom' },
    'great britain': { p: '+44', f: '🇬🇧', name: 'United Kingdom' },
    'united states': { p: '+1', f: '🇺🇸', name: 'United States' },
    'united states of america': { p: '+1', f: '🇺🇸', name: 'United States' },
    usa: { p: '+1', f: '🇺🇸', name: 'United States' },
    us: { p: '+1', f: '🇺🇸', name: 'United States' },
    uruguay: { p: '+598', f: '🇺🇾', name: 'Uruguay' },
    uzbekistan: { p: '+998', f: '🇺🇿', name: 'Uzbekistan' },
    venezuela: { p: '+58', f: '🇻🇪', name: 'Venezuela' },
    vietnam: { p: '+84', f: '🇻🇳', name: 'Vietnam' },
    yemen: { p: '+967', f: '🇾🇪', name: 'Yemen' },
    zambia: { p: '+260', f: '🇿🇲', name: 'Zambia' },
    zimbabwe: { p: '+263', f: '🇿🇼', name: 'Zimbabwe' }
  };

  const dynamicCountryPhoneCache = new Map();
  const pendingCountryPhoneLookups = new Map();
  const dynamicPrefixCountryCache = new Map();
  const pendingPrefixCountryLookups = new Map();
  const countryCoordinatesCache = new Map();
  const pendingCountryCoordinatesLookups = new Map();
  let allCountriesPrefixPreloadPromise = null;

  const normalizeCountryKey = (value) => String(value || '')
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\(.*?\)/g, ' ')
    .replace(/[^a-z\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

  const FRENCH_COUNTRY_TO_ENGLISH = {
    'afrique du sud': 'South Africa',
    albanie: 'Albania',
    algerie: 'Algeria',
    allemagne: 'Germany',
    andorre: 'Andorra',
    angleterre: 'United Kingdom',
    angola: 'Angola',
    'arabie saoudite': 'Saudi Arabia',
    argentine: 'Argentina',
    armenie: 'Armenia',
    australie: 'Australia',
    autriche: 'Austria',
    azerbaidjan: 'Azerbaijan',
    bahrein: 'Bahrain',
    bangladesh: 'Bangladesh',
    bielorussie: 'Belarus',
    belgique: 'Belgium',
    benin: 'Benin',
    bhoutan: 'Bhutan',
    bolivie: 'Bolivia',
    bosnie: 'Bosnia and Herzegovina',
    bresil: 'Brazil',
    bulgarie: 'Bulgaria',
    'burkina faso': 'Burkina Faso',
    burundi: 'Burundi',
    cambodge: 'Cambodia',
    cameroun: 'Cameroon',
    canada: 'Canada',
    chili: 'Chile',
    chine: 'China',
    chypre: 'Cyprus',
    colombie: 'Colombia',
    congo: 'Congo',
    'coree du nord': 'North Korea',
    'coree du sud': 'South Korea',
    coree: 'South Korea',
    'costa rica': 'Costa Rica',
    'cote d ivoire': "Cote d'Ivoire",
    croatie: 'Croatia',
    cuba: 'Cuba',
    danemark: 'Denmark',
    djibouti: 'Djibouti',
    egypte: 'Egypt',
    'emirats arabes unis': 'United Arab Emirates',
    equateur: 'Ecuador',
    espagne: 'Spain',
    estonie: 'Estonia',
    'etats unis': 'United States',
    'etats unis d amerique': 'United States',
    ethiopie: 'Ethiopia',
    finlande: 'Finland',
    france: 'France',
    gabon: 'Gabon',
    gambie: 'Gambia',
    georgie: 'Georgia',
    ghana: 'Ghana',
    grece: 'Greece',
    guinee: 'Guinea',
    haiti: 'Haiti',
    hongrie: 'Hungary',
    inde: 'India',
    indonesie: 'Indonesia',
    irak: 'Iraq',
    iran: 'Iran',
    irlande: 'Ireland',
    islande: 'Iceland',
    israel: 'Israel',
    italie: 'Italy',
    japon: 'Japan',
    jordanie: 'Jordan',
    kazakhstan: 'Kazakhstan',
    kenya: 'Kenya',
    koweit: 'Kuwait',
    liban: 'Lebanon',
    libye: 'Libya',
    lithuanie: 'Lithuania',
    luxembourg: 'Luxembourg',
    madagascar: 'Madagascar',
    malaisie: 'Malaysia',
    mali: 'Mali',
    malte: 'Malta',
    maroc: 'Morocco',
    maurice: 'Mauritius',
    mexique: 'Mexico',
    moldavie: 'Moldova',
    mongolie: 'Mongolia',
    montenegro: 'Montenegro',
    mozambique: 'Mozambique',
    namibie: 'Namibia',
    nepal: 'Nepal',
    niger: 'Niger',
    nigeria: 'Nigeria',
    norvege: 'Norway',
    'nouvelle zelande': 'New Zealand',
    oman: 'Oman',
    ouganda: 'Uganda',
    ouzbekistan: 'Uzbekistan',
    pakistan: 'Pakistan',
    palestine: 'Palestine',
    panama: 'Panama',
    paraguay: 'Paraguay',
    'pays bas': 'Netherlands',
    perou: 'Peru',
    philippines: 'Philippines',
    pologne: 'Poland',
    portugal: 'Portugal',
    qatar: 'Qatar',
    'republique centrafricaine': 'Central African Republic',
    'republique democratique du congo': 'Democratic Republic of the Congo',
    'republique dominicaine': 'Dominican Republic',
    'republique tcheque': 'Czech Republic',
    roumanie: 'Romania',
    'royaume uni': 'United Kingdom',
    russie: 'Russia',
    rwanda: 'Rwanda',
    senegal: 'Senegal',
    serbie: 'Serbia',
    singapour: 'Singapore',
    slovaquie: 'Slovakia',
    slovenie: 'Slovenia',
    somalie: 'Somalia',
    soudan: 'Sudan',
    'sri lanka': 'Sri Lanka',
    suede: 'Sweden',
    suisse: 'Switzerland',
    syrie: 'Syria',
    taiwan: 'Taiwan',
    tanzanie: 'Tanzania',
    tchad: 'Chad',
    thailande: 'Thailand',
    togo: 'Togo',
    'trinite et tobago': 'Trinidad and Tobago',
    tunisie: 'Tunisia',
    turkmenistan: 'Turkmenistan',
    turquie: 'Turkey',
    ukraine: 'Ukraine',
    uruguay: 'Uruguay',
    venezuela: 'Venezuela',
    vietnam: 'Vietnam',
    yemen: 'Yemen',
    zambie: 'Zambia',
    zimbabwe: 'Zimbabwe'
  };

  const COUNTRY_CANONICAL_NAME_BY_KEY = Object.create(null);
  let globeCountryCanonicalMap = null;

  const getGlobeCountryCanonicalMap = () => {
    if (globeCountryCanonicalMap) return globeCountryCanonicalMap;

    const map = new Map();
    if (Array.isArray(window.COUNTRIES)) {
      window.COUNTRIES.forEach((entry) => {
        const canonical = String(entry?.name || '').trim();
        if (!canonical) return;

        const key = normalizeCountryKey(canonical);
        if (key) map.set(key, canonical);

        if (Array.isArray(entry?.aliases)) {
          entry.aliases.forEach((alias) => {
            const aliasKey = normalizeCountryKey(alias);
            if (aliasKey) map.set(aliasKey, canonical);
          });
        }
      });
    }

    globeCountryCanonicalMap = map;
    return globeCountryCanonicalMap;
  };

  const toCanonicalEnglishCountryName = (value) => {
    const raw = String(value || '').trim();
    if (!raw) return '';

    const normalized = normalizeCountryKey(raw);
    if (!normalized) return raw;

    if (COUNTRY_CANONICAL_NAME_BY_KEY[normalized]) {
      return COUNTRY_CANONICAL_NAME_BY_KEY[normalized];
    }

    if (dynamicCountryPhoneCache.has(normalized)) {
      const cached = dynamicCountryPhoneCache.get(normalized);
      if (cached?.name) return cached.name;
    }

    const globeMap = getGlobeCountryCanonicalMap();
    if (globeMap.has(normalized)) {
      return globeMap.get(normalized);
    }

    return raw;
  };

  const regionToFlag = (countryCode = '') => {
    const cc = String(countryCode || '').trim().toUpperCase();
    if (!/^[A-Z]{2}$/.test(cc)) return '🌍';
    return String.fromCodePoint(...[...cc].map((char) => 127397 + char.charCodeAt(0)));
  };

  const resolveCountryPhoneInfo = (countryValue) => {
    const canonicalCountry = toCanonicalEnglishCountryName(countryValue);
    const normalized = normalizeCountryKey(canonicalCountry);
    if (!normalized) return null;
    if (COUNTRY_PHONE_DATA[normalized]) return COUNTRY_PHONE_DATA[normalized];
    if (dynamicCountryPhoneCache.has(normalized)) return dynamicCountryPhoneCache.get(normalized);

    return null;
  };

  const rememberCountryInfo = (info, aliases = []) => {
    if (!info || !info.p) return;

    const normalizedPrefix = String(info.p || '').trim().replace(/\s+/g, '');
    if (!/^\+\d{1,4}$/.test(normalizedPrefix)) return;

    const canonical = {
      p: normalizedPrefix,
      f: String(info.f || '🌍').trim() || '🌍',
      name: String(info.name || '').trim() || 'Unknown',
    };

    const allAliases = [canonical.name, ...aliases]
      .map((entry) => normalizeCountryKey(entry))
      .filter(Boolean);

    allAliases.forEach((aliasKey) => {
      dynamicCountryPhoneCache.set(aliasKey, canonical);
    });

    if (!dynamicPrefixCountryCache.has(canonical.p)) {
      dynamicPrefixCountryCache.set(canonical.p, canonical);
    }
  };

  const resolveCountryPhoneInfoAsync = async (countryValue) => {
    const canonicalCountry = toCanonicalEnglishCountryName(countryValue);
    const normalized = normalizeCountryKey(canonicalCountry);
    if (!normalized) return null;

    const immediate = resolveCountryPhoneInfo(canonicalCountry);
    if (immediate) return immediate;

    if (pendingCountryPhoneLookups.has(normalized)) {
      return pendingCountryPhoneLookups.get(normalized);
    }

    const lookupPromise = (async () => {
      const query = encodeURIComponent(String(canonicalCountry || countryValue || '').trim());
      let rows = [];

      try {
        let response = await fetch(`https://restcountries.com/v3.1/name/${query}?fullText=true&fields=name,idd,cca2,flag,flags`);
        if (!response.ok) {
          response = await fetch(`https://restcountries.com/v3.1/name/${query}?fields=name,idd,cca2,flag,flags`);
        }
        if (!response.ok) return null;
        rows = await response.json();
      } catch (_error) {
        return null;
      }

      if (!Array.isArray(rows) || rows.length === 0) return null;

      const target = rows.find((entry) => {
        const common = normalizeCountryKey(entry?.name?.common || '');
        const official = normalizeCountryKey(entry?.name?.official || '');
        return common === normalized || official === normalized;
      }) || rows[0];

      const root = String(target?.idd?.root || '').trim();
      const suffixes = Array.isArray(target?.idd?.suffixes) ? target.idd.suffixes : [];
      const suffix = String(suffixes[0] || '').trim();
      const prefix = `${root}${suffix}`.replace(/\s+/g, '');
      if (!/^\+\d{1,4}$/.test(prefix)) return null;

      const name = String(target?.name?.common || canonicalCountry || countryValue || '').trim() || String(canonicalCountry || countryValue || '').trim();
      const officialName = String(target?.name?.official || '').trim();
      const canonicalName = toCanonicalEnglishCountryName(name);
      const info = {
        p: prefix,
        f: String(target?.flag || '').trim() || regionToFlag(target?.cca2) || '🌍',
        name: canonicalName,
      };

      rememberCountryInfo(info, [normalized, canonicalName, name, officialName, canonicalCountry, countryValue]);
      return info;
    })();

    pendingCountryPhoneLookups.set(normalized, lookupPromise);
    try {
      return await lookupPromise;
    } finally {
      pendingCountryPhoneLookups.delete(normalized);
    }
  };

  Object.keys(COUNTRY_PHONE_DATA).forEach((countryKey) => {
    const info = COUNTRY_PHONE_DATA[countryKey];
    const normalizedKey = normalizeCountryKey(countryKey);
    const normalizedName = normalizeCountryKey(info?.name);
    if (normalizedKey && info?.name) {
      COUNTRY_CANONICAL_NAME_BY_KEY[normalizedKey] = info.name;
    }
    if (normalizedName && info?.name) {
      COUNTRY_CANONICAL_NAME_BY_KEY[normalizedName] = info.name;
    }
    rememberCountryInfo(info, [countryKey, info?.name]);
  });

  Object.entries(FRENCH_COUNTRY_TO_ENGLISH).forEach(([frenchName, englishName]) => {
    const frenchKey = normalizeCountryKey(frenchName);
    if (frenchKey) {
      COUNTRY_CANONICAL_NAME_BY_KEY[frenchKey] = englishName;
    }
  });

  const preloadAllCountriesPhoneDataAsync = async () => {
    if (allCountriesPrefixPreloadPromise) {
      return allCountriesPrefixPreloadPromise;
    }

    allCountriesPrefixPreloadPromise = (async () => {
      let rows = [];

      try {
        const response = await fetch('https://restcountries.com/v3.1/all?fields=name,idd,cca2,flag,altSpellings');
        if (!response.ok) return;
        rows = await response.json();
      } catch (_error) {
        return;
      }

      if (!Array.isArray(rows) || rows.length === 0) return;

      rows.forEach((entry) => {
        const root = String(entry?.idd?.root || '').trim();
        const suffixes = Array.isArray(entry?.idd?.suffixes) ? entry.idd.suffixes : [];
        if (!root || !suffixes.length) return;

        const commonName = String(entry?.name?.common || '').trim();
        const officialName = String(entry?.name?.official || '').trim();
        const altSpellings = Array.isArray(entry?.altSpellings) ? entry.altSpellings : [];
        const baseFlag = String(entry?.flag || '').trim() || regionToFlag(entry?.cca2) || '🌍';

        suffixes.forEach((suffixValue) => {
          const suffix = String(suffixValue || '').trim();
          const prefix = `${root}${suffix}`.replace(/\s+/g, '');
          if (!/^\+\d{1,4}$/.test(prefix)) return;

          const info = {
            p: prefix,
            f: baseFlag,
            name: commonName || officialName || 'Unknown',
          };

          rememberCountryInfo(info, [commonName, officialName, ...altSpellings]);
        });
      });
    })();

    try {
      await allCountriesPrefixPreloadPromise;
    } catch (_error) {
    }
  };

  const findCountryInfoByPrefix = (prefix) => {
    const normalizedPrefix = String(prefix || '').trim().replace(/\s+/g, '');
    if (!/^\+\d{1,4}$/.test(normalizedPrefix)) return null;

    if (dynamicPrefixCountryCache.has(normalizedPrefix)) {
      return dynamicPrefixCountryCache.get(normalizedPrefix);
    }

    const digits = normalizedPrefix.replace(/^\+/, '');
    for (let len = Math.min(4, digits.length); len >= 1; len -= 1) {
      const candidate = `+${digits.slice(0, len)}`;
      if (dynamicPrefixCountryCache.has(candidate)) {
        return dynamicPrefixCountryCache.get(candidate);
      }
    }

    return null;
  };

  const resolveCountryFromPrefixAsync = async (phoneValue) => {
    const dialPrefix = extractDialPrefix(phoneValue);
    if (!dialPrefix) return null;

    const immediate = findCountryInfoByPrefix(dialPrefix);
    if (immediate) return immediate;

    if (pendingPrefixCountryLookups.has(dialPrefix)) {
      return pendingPrefixCountryLookups.get(dialPrefix);
    }

    const lookupPromise = (async () => {
      const digits = String(dialPrefix || '').replace(/\D/g, '');
      if (!digits) return null;

      for (let len = Math.min(4, digits.length); len >= 1; len -= 1) {
        const code = digits.slice(0, len);
        if (!code) continue;

        let rows = [];
        try {
          const response = await fetch(`https://restcountries.com/v3.1/callingcode/${encodeURIComponent(code)}?fields=name,idd,cca2,flag`);
          if (!response.ok) continue;
          rows = await response.json();
        } catch (_error) {
          continue;
        }

        if (!Array.isArray(rows) || rows.length === 0) continue;

        const preferred = rows.find((entry) => {
          const root = String(entry?.idd?.root || '').trim();
          const suffixes = Array.isArray(entry?.idd?.suffixes) ? entry.idd.suffixes : [];
          return suffixes.some((suffix) => `${root}${String(suffix || '').trim()}`.replace(/\s+/g, '') === dialPrefix);
        }) || rows[0];

        const root = String(preferred?.idd?.root || '').trim();
        const suffixes = Array.isArray(preferred?.idd?.suffixes) ? preferred.idd.suffixes : [];
        const matchedSuffix = suffixes.find((suffix) => `${root}${String(suffix || '').trim()}`.replace(/\s+/g, '') === dialPrefix) || suffixes[0] || '';
        const computedPrefix = `${root}${String(matchedSuffix || '').trim()}`.replace(/\s+/g, '');
        if (!/^\+\d{1,4}$/.test(computedPrefix)) continue;

        const info = {
          p: computedPrefix,
          f: String(preferred?.flag || '').trim() || regionToFlag(preferred?.cca2),
          name: String(preferred?.name?.common || '').trim() || 'Unknown',
        };

        rememberCountryInfo(info, [preferred?.name?.official, preferred?.name?.common]);

        const resolved = findCountryInfoByPrefix(dialPrefix);
        if (resolved) return resolved;
      }

      return null;
    })();

    pendingPrefixCountryLookups.set(dialPrefix, lookupPromise);
    try {
      return await lookupPromise;
    } finally {
      pendingPrefixCountryLookups.delete(dialPrefix);
    }
  };

  const resolveCountryCoordinatesAsync = async (countryName) => {
    const canonicalCountry = toCanonicalEnglishCountryName(countryName);
    const normalized = normalizeCountryKey(canonicalCountry);
    if (!normalized) return null;

    if (countryCoordinatesCache.has(normalized)) {
      return countryCoordinatesCache.get(normalized);
    }

    if (pendingCountryCoordinatesLookups.has(normalized)) {
      return pendingCountryCoordinatesLookups.get(normalized);
    }

    const lookupPromise = (async () => {
      const query = encodeURIComponent(String(canonicalCountry || countryName || '').trim());
      let rows = [];

      try {
        let response = await fetch(`https://restcountries.com/v3.1/name/${query}?fullText=true&fields=name,capitalInfo,latlng`);
        if (!response.ok) {
          response = await fetch(`https://restcountries.com/v3.1/name/${query}?fields=name,capitalInfo,latlng`);
        }
        if (!response.ok) return null;
        rows = await response.json();
      } catch (_error) {
        return null;
      }

      if (!Array.isArray(rows) || !rows.length) return null;

      const preferred = rows.find((entry) => {
        const common = normalizeCountryKey(entry?.name?.common || '');
        const official = normalizeCountryKey(entry?.name?.official || '');
        return common === normalized || official === normalized;
      }) || rows[0];

      const coords = preferred?.capitalInfo?.latlng || preferred?.latlng || null;
      if (!Array.isArray(coords) || coords.length < 2) return null;

      const result = {
        lat: Number(coords[0]),
        lng: Number(coords[1]),
      };

      if (!Number.isFinite(result.lat) || !Number.isFinite(result.lng)) return null;

      countryCoordinatesCache.set(normalized, result);
      return result;
    })();

    pendingCountryCoordinatesLookups.set(normalized, lookupPromise);
    try {
      return await lookupPromise;
    } finally {
      pendingCountryCoordinatesLookups.delete(normalized);
    }
  };

  const pointCountryOnPicker = async (form, countryName) => {
    const country = toCanonicalEnglishCountryName(countryName);
    if (!country) return;

    focusCountryPicker(form);

    const coords = await resolveCountryCoordinatesAsync(country);
    if (!coords) {
      window.dispatchEvent(new CustomEvent('user-country-inferred', {
        detail: { country },
      }));
      return;
    }

    if (window.GlobeExplorer && typeof window.GlobeExplorer.setView === 'function') {
      window.GlobeExplorer.setView([coords.lat, coords.lng], 5);
    }

    if (state.mapReady && state.map) {
      state.map.setView([coords.lat, coords.lng], 5);
      if (window.L) {
        if (state.marker) {
          state.marker.setLatLng([coords.lat, coords.lng]);
        } else {
          state.marker = window.L.marker([coords.lat, coords.lng]).addTo(state.map);
        }
      }
    }

    window.dispatchEvent(new CustomEvent('user-country-inferred', {
      detail: { country, lat: coords.lat, lng: coords.lng },
    }));
  };

  const inferCountryFromPhoneInput = async (phoneInput, options = {}) => {
    if (!phoneInput) return;

    const shouldPoint = options.point === true;
    const countryInput = relatedCountryInput(phoneInput);
    if (!countryInput) return;

    const inferredInfo = await resolveCountryFromPrefixAsync(String(phoneInput.value || '').trim());
    if (!inferredInfo) return;

    const currentCountryValue = String(countryInput.value || '').trim();
    const currentInfo = resolveCountryPhoneInfo(currentCountryValue);

    const shouldSetCountry = !currentCountryValue
      || /^unknown$/i.test(currentCountryValue)
      || !currentInfo
      || currentInfo.p !== inferredInfo.p;

    if (shouldSetCountry) {
      countryInput.value = toCanonicalEnglishCountryName(inferredInfo.name);
      setCountryMeta(countryInput, inferredInfo);
      countryInput.dispatchEvent(new Event('input', { bubbles: true }));
      countryInput.dispatchEvent(new Event('change', { bubbles: true }));
    } else {
      setCountryMeta(countryInput, currentInfo || inferredInfo);
    }

    markCountryPhoneInteracted(countryInput, phoneInput);

    if (shouldPoint) {
      await pointCountryOnPicker(phoneInput.closest('form'), inferredInfo.name);
    }
  };

  const setCountryMeta = (countryInput, info, isLoading = false) => {
    const group = countryInput?.closest('.uf-group');
    if (!group) return;
    const flagNode = group.querySelector('.uf-country-flag');
    const prefixNode = group.querySelector('.uf-country-prefix');
    if (flagNode) flagNode.textContent = info?.f || '🌍';
    if (prefixNode) {
      if (info?.p) {
        prefixNode.textContent = info.p;
      } else if (isLoading) {
        prefixNode.textContent = 'Detecting prefix...';
      } else {
        prefixNode.textContent = 'Select country to set prefix';
      }
    }
  };

  const relatedPhoneInput = (countryInput) => {
    const form = countryInput?.closest('form');
    if (form) {
      const field = form.querySelector('#phone, #formPhone');
      if (field) return field;
    }
    return document.getElementById('phone') || document.getElementById('formPhone');
  };

  const relatedCountryInput = (phoneInput) => {
    const form = phoneInput?.closest('form');
    if (form) {
      const field = form.querySelector('#country, #formCountry');
      if (field) return field;
    }
    return document.getElementById('country') || document.getElementById('formCountry');
  };

  const focusCountryPicker = (form) => {
    const picker = form?.querySelector('#userGlobeMap, #profileUserGlobeMap, #map')
      || document.getElementById('userGlobeMap')
      || document.getElementById('profileUserGlobeMap')
      || document.getElementById('map');

    if (typeof window.openMapPicker === 'function' && picker?.id === 'map') {
      window.openMapPicker();
      return;
    }

    if (picker) {
      picker.setAttribute('tabindex', '-1');
      picker.focus({ preventScroll: false });
      picker.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  };

  const showPrefixMismatchWarning = (phoneInput, countryName, info) => {
    const message = `The phone prefix must match ${info.f} ${countryName} (${info.p}).`;
    setFieldError(phoneInput, message);
  };

  const buildPhoneWithCountryPrefix = (phoneRaw, info) => {
    const localPart = stripCountryPrefix(phoneRaw, info);
    if (!localPart) return `${info.p} `;
    return `${info.p}${localPart}`;
  };

  const warnPrefixMismatchOnce = (phoneInput, info, currentPrefix = '') => {
    if (!phoneInput || !info || !currentPrefix || currentPrefix === info.p) {
      if (phoneInput) phoneInput.dataset.phoneMismatchKey = '';
      return;
    }

    const signature = `${String(info.name || '').toLowerCase()}::${info.p}::${currentPrefix}`;
    if (phoneInput.dataset.phoneMismatchKey === signature) return;

    phoneInput.dataset.phoneMismatchKey = signature;
    showPrefixMismatchWarning(phoneInput, info.name, info);
  };

  const keepPhoneFieldActive = (phoneInput) => {
    const group = phoneInput?.closest('.uf-group');
    if (!group) return;
    group.classList.add('uf-phone-required');
    if (String(phoneInput.value || '').trim()) {
      group.classList.add('has-value');
    }
  };

  const markCountryPhoneInteracted = (countryInput, phoneInput) => {
    if (countryInput) countryInput.dataset.cpTouched = '1';
    if (phoneInput) phoneInput.dataset.cpTouched = '1';
  };

  const wasCountryPhoneInteracted = (countryInput, phoneInput) => {
    return (countryInput?.dataset.cpTouched === '1') || (phoneInput?.dataset.cpTouched === '1');
  };

  const enforceLockedPrefix = (phoneInput) => {
    if (!phoneInput) return;
    const countryInput = relatedCountryInput(phoneInput);
    const info = resolveCountryPhoneInfo(String(countryInput?.value || '').trim());
    if (!info) return;

    const currentRaw = String(phoneInput.value || '').trim();
    const lockedValue = buildPhoneWithCountryPrefix(currentRaw, info);
    if (currentRaw !== lockedValue) {
      phoneInput.value = lockedValue;
    }
    keepPhoneFieldActive(phoneInput);
  };

  const passwordMessage = (value, required = true) => {
    const raw = String(value || '');
    if (!raw && !required) return '';
    if (!raw) return 'Password is required.';
    if (raw.length < 10) return 'Password must be at least 10 characters.';
    if (raw.length > 128) return 'Password must be at most 128 characters.';
    if (/\s/.test(raw)) return 'Password cannot contain spaces.';
    if (!/[a-z]/.test(raw)) return 'Password needs at least one lowercase letter.';
    if (!/[A-Z]/.test(raw)) return 'Password needs at least one uppercase letter.';
    if (!/[0-9]/.test(raw)) return 'Password needs at least one number.';
    if (!/[^A-Za-z0-9]/.test(raw)) return 'Password needs at least one symbol.';
    return '';
  };

  const fieldKey = (field) => {
    const key = (field.name || field.id || '').trim();
    return key.toLowerCase();
  };

  const isLocationKey = (key) => key === 'country' || key === 'formcountry' || key === 'editlocation';

  const isCreateMode = (form) => {
    const idField = form.querySelector('#formId');
    return !idField || !String(idField.value || '').trim();
  };

  const getFieldContainer = (field) => {
    if (!field) return null;
    return field.closest('.uf-group')
      || field.closest('.space-y-2')
      || field.closest('.form-group')
      || field.parentElement;
  };

  const getFieldErrorNode = (field, create = false) => {
    const container = getFieldContainer(field);
    if (!container) return null;

    let node = container.querySelector('.field-error');
    if (!node && create) {
      node = document.createElement('div');
      node.className = 'field-error';
      node.hidden = true;
      container.appendChild(node);
    }

    return node;
  };

  const setFieldError = (field, message) => {
    if (!field) return;

    const text = String(message || '').trim();
    const hasError = text.length > 0;
    const container = getFieldContainer(field);
    const group = field.closest('.uf-group');

    if (container) {
      container.classList.toggle('has-error', hasError);
    }

    if (group) {
      group.classList.toggle('is-invalid', hasError);
    }

    field.classList.toggle('is-invalid', hasError);
    field.dataset.invalid = hasError ? '1' : '0';
    field.setAttribute('aria-invalid', hasError ? 'true' : 'false');
    field.style.borderColor = '';

    const errorNode = getFieldErrorNode(field, hasError);
    if (errorNode) {
      errorNode.textContent = text;
      errorNode.hidden = !hasError;
    }
  };

  const clearFieldError = (field) => {
    setFieldError(field, '');
  };

  const validateField = (field, context = 'generic') => {
    if (!field || field.disabled) return true;

    const key = fieldKey(field);
    if (key === '' || field.type === 'hidden' || field.type === 'button' || field.type === 'submit') {
      return true;
    }

    const value = String(field.value || '').trim();
    const currentContext = String(context || 'generic').toLowerCase();
    const form = field.closest('form');
    const createMode = form ? isCreateMode(form) : false;

    let message = '';

    if (key === 'first_name' || key === 'firstname' || key === 'formfirstname' || key === 'editfirstname' || key === 'firstName') {
      if (!isName(value)) {
        message = 'First name must be 2-40 letters only.';
      } else if (!startsWithUppercaseLetter(value)) {
        message = 'First name must start with an uppercase letter.';
      }
    }

    if (key === 'last_name' || key === 'lastname' || key === 'formlastname' || key === 'editlastname' || key === 'lastName') {
      if (!isName(value)) {
        message = 'Last name must be 2-40 letters only.';
      } else if (!startsWithUppercaseLetter(value)) {
        message = 'Last name must start with an uppercase letter.';
      }
    }

    if (key === 'email' || key === 'formemail' || key === 'editemail') {
      if (!isEmail(value) || value.length < 6 || value.length > 190) {
        message = 'Email format is invalid.';
      }
    }

    if (key === 'password' || key === 'formpassword' || key === 'reg-password') {
      if (currentContext === 'auth-login') {
        const raw = String(field.value || '');
        if (!raw.trim()) {
          message = 'Password is required.';
        } else if (raw.length > 128) {
          message = 'Password is too long.';
        }
      } else {
        const required = currentContext === 'auth-register' || (currentContext === 'dashboard' && createMode);
        message = passwordMessage(field.value, required);
      }
    }

    if (key === 'confirm_password' || key === 'confirmpassword' || key === 'confirm-password') {
      const passwordField = form?.querySelector('#password, #reg-password, #formPassword');
      const sourcePassword = String(passwordField?.value || '');
      if (!value) {
        message = 'Please confirm your password.';
      } else if (value !== sourcePassword) {
        message = 'Confirm password must match password.';
      }
    }

    if (key === 'phone' || key === 'formphone' || key === 'editphone') {
      if (!value) {
        message = 'Phone number is required.';
      } else if (!isPhone(value)) {
        message = 'Phone must start with +country code and contain 8 to 15 digits.';
      } else {
        const countryField = relatedCountryInput(field);
        const countryValue = String(countryField?.value || '').trim();
        const countryInfo = resolveCountryPhoneInfo(countryValue);
        const phoneNormalized = normalizePhone(value);
        const interacted = wasCountryPhoneInteracted(countryField, field);
        const countryPrefixDigits = getCountryPrefixDigits(countryInfo);
        const phoneDigits = phoneNormalized.replace(/^\+/, '');
        if (countryInfo && interacted && phoneDigits && countryPrefixDigits && !phoneDigits.startsWith(countryPrefixDigits)) {
          message = `Phone prefix must match ${countryInfo.f} ${countryInfo.name} (${countryInfo.p}).`;
          const currentPrefix = `+${phoneDigits.slice(0, countryPrefixDigits.length)}`;
          warnPrefixMismatchOnce(field, countryInfo, currentPrefix);
        }
      }
    }

    if (isLocationKey(key)) {
      const hasCountry = value && value.length >= 2 && value.length <= 80;
      const mapAddress = String(form?.querySelector('#formMapAddress, #fullAddress')?.value || '').trim();
      const latValue = String(form?.querySelector('#formLatitude, #latitude')?.value || '').trim();
      const lngValue = String(form?.querySelector('#formLongitude, #longitude')?.value || '').trim();
      const hasCoords = latValue !== '' && lngValue !== '';
      const hasLocationHint = mapAddress.length >= 2 || hasCoords;

      if (!hasCountry && !hasLocationHint) {
        message = 'Please select a valid country or map location.';
      }
    }

    if (key === 'title' || key === 'formtitle' || key === 'edittitle') {
      if (value && (value.length < 2 || value.length > 80)) {
        message = 'Title must be 2-80 characters.';
      }
    }

    if (key === 'skills' || key === 'formskills' || key === 'editskills') {
      if (value && (value.length < 2 || value.length > 250)) {
        message = 'Skills must be 2-250 characters.';
      }
    }

    if (key === 'bio' || key === 'formbio' || key === 'editbio') {
      if (value && (value.length < 20 || value.length > 600)) {
        message = 'Bio must be between 20 and 600 characters.';
      }
    }

    if (key === 'role' || key === 'formrole') {
      const validRoles = ['client', 'freelancer'];
      if (value && !validRoles.includes(value.toLowerCase())) {
        message = 'Role value is invalid.';
      }
    }

    if (key === 'xp' || key === 'formxp') {
      const xp = Number(value || 0);
      if (Number.isNaN(xp) || xp < 0 || xp > 100000) {
        message = 'XP must be a number between 0 and 100000.';
      }
    }

    if (message) {
      setFieldError(field, message);
      return false;
    }

    clearFieldError(field);
    return true;
  };

  const validateForm = (form, context = 'generic') => {
    if (!form) return true;

    const fields = Array.from(form.querySelectorAll('input, select, textarea')).filter((field) => {
      const key = fieldKey(field);
      return key !== '' && key !== 'remember_me' && field.type !== 'hidden' && field.type !== 'button' && field.type !== 'submit';
    });

    for (const field of fields) {
      if (!validateField(field, context)) {
        field.focus();
        return false;
      }
    }

    return true;
  };

  const attachLiveValidation = (form, context = 'generic') => {
    if (!form) return;

    const fields = form.querySelectorAll('input, select, textarea');
    fields.forEach((field) => {
      const run = () => validateField(field, context);
      field.addEventListener('input', run);
      field.addEventListener('blur', run);
      field.addEventListener('change', run);
    });
  };

  const resolveCountryName = (address = {}) => {
    const direct = String(
      address.country ||
      address.country_name ||
      address['ISO3166-2-lvl4'] ||
      ''
    ).trim();

    if (direct && !/unknown/i.test(direct)) {
      return toCanonicalEnglishCountryName(direct);
    }

    const cc = String(address.country_code || '').trim().toUpperCase();
    if (cc) {
      try {
        const formatter = new Intl.DisplayNames(['en'], { type: 'region' });
        const mapped = formatter.of(cc);
        if (mapped && !/unknown/i.test(mapped)) {
          return toCanonicalEnglishCountryName(mapped);
        }
      } catch (_error) {
      }
    }

    const fallback = String(address.state || address.county || address.city || address.town || address.village || '').trim();
    return toCanonicalEnglishCountryName(fallback || '');
  };

  const authMapElements = () => ({
    modal: document.getElementById('locationPickerModal'),
    mapNode: document.getElementById('map'),
    countryInput: document.getElementById('country'),
    latitudeInput: document.getElementById('latitude'),
    longitudeInput: document.getElementById('longitude'),
    cityInput: document.getElementById('city'),
    fullAddressInput: document.getElementById('fullAddress'),
    selectedDisplay: document.getElementById('selectedLocationDisplay'),
    confirmBtn: document.getElementById('confirmLocationBtn'),
  });

  const initMapIfNeeded = () => {
    const { mapNode } = authMapElements();
    if (!mapNode || state.mapReady || !window.L) return;

    state.map = window.L.map(mapNode).setView([25, 5], 2);
    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 18,
    }).addTo(state.map);

    state.map.on('click', async (event) => {
      const lat = Number(event.latlng.lat.toFixed(6));
      const lng = Number(event.latlng.lng.toFixed(6));

      if (state.marker) {
        state.marker.setLatLng([lat, lng]);
      } else {
        state.marker = window.L.marker([lat, lng]).addTo(state.map);
      }

      let country = '';
      let city = '';
      let fullAddress = '';

      try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`);
        const data = await response.json();
        const address = data?.address || {};

        country = resolveCountryName(address);
        city = String(address.city || address.town || address.village || address.state || '').trim();
        fullAddress = String(data?.display_name || '').trim();
      } catch (_error) {
      }

      if (!country) {
        country = `Location (${lat.toFixed(3)}, ${lng.toFixed(3)})`;
      }

      state.selectedLocation = { lat, lng, country, city, fullAddress: fullAddress || country };
      refreshMapSelectionUI();
    });

    state.mapReady = true;
  };

  const refreshMapSelectionUI = () => {
    const { selectedDisplay, confirmBtn } = authMapElements();
    if (!selectedDisplay || !confirmBtn) return;

    if (!state.selectedLocation) {
      selectedDisplay.textContent = 'None';
      confirmBtn.disabled = true;
      return;
    }

    const cityPart = state.selectedLocation.city ? `${state.selectedLocation.city}, ` : '';
    selectedDisplay.textContent = `${cityPart}${state.selectedLocation.country}`;
    confirmBtn.disabled = false;
  };

  const openMapPicker = () => {
    const { modal } = authMapElements();
    if (!modal) return;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    initMapIfNeeded();

    setTimeout(() => {
      if (state.map) {
        state.map.invalidateSize();
      }
    }, 110);
  };

  const closeMapPicker = () => {
    const { modal } = authMapElements();
    if (!modal) return;

    modal.classList.add('hidden');
    modal.classList.remove('flex');
  };

  const confirmLocation = () => {
    const refs = authMapElements();
    if (!state.selectedLocation || !refs.countryInput) {
      if (refs.countryInput) {
        setFieldError(refs.countryInput, 'Please choose a location on the map first.');
      }
      return;
    }

    refs.countryInput.value = toCanonicalEnglishCountryName(state.selectedLocation.country);
    refs.countryInput.dispatchEvent(new Event('change', { bubbles: true }));
    if (refs.latitudeInput) refs.latitudeInput.value = String(state.selectedLocation.lat);
    if (refs.longitudeInput) refs.longitudeInput.value = String(state.selectedLocation.lng);
    if (refs.cityInput) refs.cityInput.value = state.selectedLocation.city || '';
    if (refs.fullAddressInput) refs.fullAddressInput.value = state.selectedLocation.fullAddress || state.selectedLocation.country;

    clearFieldError(refs.countryInput);
    validateField(refs.countryInput, 'auth-register');
    closeMapPicker();
  };

  const initValidation = () => {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const userForm = document.getElementById('userForm');
    const profileForm = document.getElementById('profileForm');

    [loginForm, registerForm, userForm, profileForm].forEach((form) => {
      if (form) form.setAttribute('novalidate', 'novalidate');
    });

    if (loginForm) {
      attachLiveValidation(loginForm, 'auth-login');
      loginForm.addEventListener('submit', (event) => {
        if (!validateForm(loginForm, 'auth-login')) {
          event.preventDefault();
        }
      });
    }

    if (registerForm) {
      attachLiveValidation(registerForm, 'auth-register');
      registerForm.addEventListener('submit', (event) => {
        if (!validateForm(registerForm, 'auth-register')) {
          event.preventDefault();
        }
      });
    }

    if (userForm) {
      attachLiveValidation(userForm, 'dashboard');
    }

    if (profileForm) {
      attachLiveValidation(profileForm, 'profile');
    }

    const countryInput = document.getElementById('country');
    if (countryInput) {
      countryInput.addEventListener('change', () => {
        if (!String(countryInput.value || '').trim()) {
          countryInput.value = 'Unknown';
        }
      });
    }

    const countryInputs = document.querySelectorAll('#country, #formCountry');
    const phoneInputs = document.querySelectorAll('#phone, #formPhone');

    void preloadAllCountriesPhoneDataAsync();

    const syncCountryAndPhone = async (countryInput, shouldWarn = true) => {
      const countryValue = String(countryInput?.value || '').trim();
      const canonicalCountryValue = toCanonicalEnglishCountryName(countryValue);

      if (countryValue && canonicalCountryValue && countryValue !== canonicalCountryValue) {
        countryInput.value = canonicalCountryValue;
      }

      let info = resolveCountryPhoneInfo(canonicalCountryValue || countryValue);
      if (!info && countryValue) {
        setCountryMeta(countryInput, null, true);
        info = await resolveCountryPhoneInfoAsync(canonicalCountryValue || countryValue);
      }

      if (info && countryInput && countryInput.value !== info.name) {
        countryInput.value = info.name;
      }

      setCountryMeta(countryInput, info);

      const phoneInput = relatedPhoneInput(countryInput);
      if (!phoneInput || !info) return;
      keepPhoneFieldActive(phoneInput);

      const phoneRaw = String(phoneInput.value || '').trim();
      const phoneNormalized = normalizePhone(phoneRaw);
      const phoneDigits = phoneNormalized.replace(/^\+/, '');
      const countryPrefixDigits = getCountryPrefixDigits(info);

      const shouldRewrite = !phoneRaw || !phoneNormalized || !phoneRaw.startsWith('+') || (phoneDigits && countryPrefixDigits && !phoneDigits.startsWith(countryPrefixDigits));
      if (shouldRewrite) {
        phoneInput.value = buildPhoneWithCountryPrefix(phoneRaw, info);
        phoneInput.dataset.phoneMismatchKey = '';
        phoneInput.dispatchEvent(new Event('input', { bubbles: true }));
      } else if (shouldWarn && phoneDigits && countryPrefixDigits) {
        const currentPrefix = `+${phoneDigits.slice(0, countryPrefixDigits.length)}`;
        warnPrefixMismatchOnce(phoneInput, info, currentPrefix);
      }
      enforceLockedPrefix(phoneInput);
    };

    const warnIfPhoneMismatch = (phoneInput) => {
      const countryInput = relatedCountryInput(phoneInput);
      const countryValue = String(countryInput?.value || '').trim();
      const info = resolveCountryPhoneInfo(countryValue);
      if (!info) return;
      const phoneRaw = String(phoneInput.value || '').trim();
      const phoneDigits = normalizePhone(phoneRaw).replace(/^\+/, '');
      const countryPrefixDigits = getCountryPrefixDigits(info);
      if (phoneDigits && countryPrefixDigits && !phoneDigits.startsWith(countryPrefixDigits)) {
        const currentPrefix = `+${phoneDigits.slice(0, countryPrefixDigits.length)}`;
        warnPrefixMismatchOnce(phoneInput, info, currentPrefix);
      } else {
        phoneInput.dataset.phoneMismatchKey = '';
      }
    };

    countryInputs.forEach((countryInput) => {
      void syncCountryAndPhone(countryInput, false);
      countryInput.addEventListener('change', () => {
        const phoneInput = relatedPhoneInput(countryInput);
        markCountryPhoneInteracted(countryInput, phoneInput);
        void syncCountryAndPhone(countryInput, true);
      });
      countryInput.addEventListener('input', () => {
        const phoneInput = relatedPhoneInput(countryInput);
        markCountryPhoneInteracted(countryInput, phoneInput);
        void syncCountryAndPhone(countryInput, false);
      });
    });

    phoneInputs.forEach((phoneInput) => {
      phoneInput.setAttribute('required', 'required');
      keepPhoneFieldActive(phoneInput);
      phoneInput.addEventListener('input', () => {
        const countryInput = relatedCountryInput(phoneInput);
        markCountryPhoneInteracted(countryInput, phoneInput);
        enforceLockedPrefix(phoneInput);
        void inferCountryFromPhoneInput(phoneInput, { point: false });
      });
      phoneInput.addEventListener('blur', () => {
        const countryInput = relatedCountryInput(phoneInput);
        markCountryPhoneInteracted(countryInput, phoneInput);
        warnIfPhoneMismatch(phoneInput);
        void inferCountryFromPhoneInput(phoneInput, { point: true });
      });
      phoneInput.addEventListener('change', () => {
        const countryInput = relatedCountryInput(phoneInput);
        markCountryPhoneInteracted(countryInput, phoneInput);
        warnIfPhoneMismatch(phoneInput);
        void inferCountryFromPhoneInput(phoneInput, { point: true });
      });
    });

    // Hide password conditionally for User Form in Edit Mode
    const idField = document.getElementById('formId');
    const pwdGroup = document.getElementById('userFormPasswordGroup');
    if (idField && pwdGroup) {
      const togglePwd = () => {
        pwdGroup.style.display = String(idField.value).trim() ? 'none' : 'flex';
      };
      idField.addEventListener('change', togglePwd);
      const observer = new MutationObserver(togglePwd);
      observer.observe(idField, { attributes: true, attributeFilter: ['value'] });
      togglePwd();
    }
  };

  window.UserValidation = {
    validateField,
    validateForm,
    attachLiveValidation,
  };

  window.openMapPicker = openMapPicker;
  window.closeMapPicker = closeMapPicker;
  window.confirmLocation = confirmLocation;

  document.addEventListener('DOMContentLoaded', () => {
    initValidation();
    refreshMapSelectionUI();
  });
})();
