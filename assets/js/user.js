(() => {
  const state = {
    lastAlertKey: '',
    map: null,
    marker: null,
    selectedLocation: null,
    mapReady: false,
  };

  const isEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
  const isName = (value) => /^[A-Za-zÀ-ÖØ-öø-ÿ'\-\s]{2,40}$/.test(value);
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
    tunisia: { p: '+216', f: '🇹🇳', name: 'Tunisia' },
    tunisie: { p: '+216', f: '🇹🇳', name: 'Tunisia' },
    france: { p: '+33', f: '🇫🇷', name: 'France' },
    iran: { p: '+98', f: '🇮🇷', name: 'Iran' },
    'iran, islamic republic of': { p: '+98', f: '🇮🇷', name: 'Iran' },
    'islamic republic of iran': { p: '+98', f: '🇮🇷', name: 'Iran' },
    germany: { p: '+49', f: '🇩🇪', name: 'Germany' },
    canada: { p: '+1', f: '🇨🇦', name: 'Canada' },
    'united states': { p: '+1', f: '🇺🇸', name: 'United States' },
    'united states of america': { p: '+1', f: '🇺🇸', name: 'United States' },
    usa: { p: '+1', f: '🇺🇸', name: 'United States' },
    'united kingdom': { p: '+44', f: '🇬🇧', name: 'United Kingdom' },
    uk: { p: '+44', f: '🇬🇧', name: 'United Kingdom' },
    england: { p: '+44', f: '🇬🇧', name: 'United Kingdom' },
    spain: { p: '+34', f: '🇪🇸', name: 'Spain' },
    italy: { p: '+39', f: '🇮🇹', name: 'Italy' },
    algeria: { p: '+213', f: '🇩🇿', name: 'Algeria' },
    morocco: { p: '+212', f: '🇲🇦', name: 'Morocco' },
    egypt: { p: '+20', f: '🇪🇬', name: 'Egypt' },
    turkey: { p: '+90', f: '🇹🇷', name: 'Turkey' },
    türkiye: { p: '+90', f: '🇹🇷', name: 'Turkey' },
    turkiye: { p: '+90', f: '🇹🇷', name: 'Turkey' }
  };

  const dynamicCountryPhoneCache = new Map();
  const pendingCountryPhoneLookups = new Map();

  const normalizeCountryKey = (value) => String(value || '')
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\(.*?\)/g, ' ')
    .replace(/[^a-z\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

  const regionToFlag = (countryCode = '') => {
    const cc = String(countryCode || '').trim().toUpperCase();
    if (!/^[A-Z]{2}$/.test(cc)) return '🌍';
    return String.fromCodePoint(...[...cc].map((char) => 127397 + char.charCodeAt(0)));
  };

  const resolveCountryPhoneInfo = (countryValue) => {
    const normalized = normalizeCountryKey(countryValue);
    if (!normalized) return null;
    if (COUNTRY_PHONE_DATA[normalized]) return COUNTRY_PHONE_DATA[normalized];
    if (dynamicCountryPhoneCache.has(normalized)) return dynamicCountryPhoneCache.get(normalized);

    return null;
  };

  const resolveCountryPhoneInfoAsync = async (countryValue) => {
    const normalized = normalizeCountryKey(countryValue);
    if (!normalized) return null;

    const immediate = resolveCountryPhoneInfo(normalized);
    if (immediate) return immediate;

    if (pendingCountryPhoneLookups.has(normalized)) {
      return pendingCountryPhoneLookups.get(normalized);
    }

    const lookupPromise = (async () => {
      const query = encodeURIComponent(String(countryValue || '').trim());
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

      const name = String(target?.name?.common || countryValue || '').trim() || String(countryValue || '').trim();
      const info = {
        p: prefix,
        f: String(target?.flag || '').trim() || regionToFlag(target?.cca2) || '🌍',
        name,
      };

      dynamicCountryPhoneCache.set(normalized, info);
      dynamicCountryPhoneCache.set(normalizeCountryKey(name), info);
      return info;
    })();

    pendingCountryPhoneLookups.set(normalized, lookupPromise);
    try {
      return await lookupPromise;
    } finally {
      pendingCountryPhoneLookups.delete(normalized);
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

  const showPrefixMismatchWarning = (countryName, info, form) => {
    const message = `The phone prefix must match ${info.f} ${countryName} (${info.p}).`;
    if (window.Swal) {
      window.Swal.fire({
        toast: true,
        icon: 'warning',
        title: message,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3600,
        timerProgressBar: true,
        background: '#0f172a',
        color: '#f8fafc',
        customClass: { container: 'uf-swal-front' }
      });
      return;
    }
    pushAlert(message, 'phone_mismatch');
  };

  const buildPhoneWithCountryPrefix = (phoneRaw, info) => {
    const localPart = stripCountryPrefix(phoneRaw, info);
    if (!localPart) return `${info.p} `;
    return `${info.p}${localPart}`;
  };

  const warnPrefixMismatchOnce = (phoneInput, info, form, currentPrefix = '') => {
    if (!phoneInput || !info || !currentPrefix || currentPrefix === info.p) {
      if (phoneInput) phoneInput.dataset.phoneMismatchKey = '';
      return;
    }

    const signature = `${String(info.name || '').toLowerCase()}::${info.p}::${currentPrefix}`;
    if (phoneInput.dataset.phoneMismatchKey === signature) return;

    phoneInput.dataset.phoneMismatchKey = signature;
    showPrefixMismatchWarning(info.name, info, form);
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

  const validateField = (field, context = 'generic', silent = false) => {
    if (!field || field.disabled) return true;

    const key = fieldKey(field);
    const value = String(field.value || '').trim();
    const currentContext = String(context || 'generic').toLowerCase();
    const form = field.closest('form');
    const createMode = form ? isCreateMode(form) : false;

    let message = '';
    let customAlertHandled = false;

    if (key === 'first_name' || key === 'firstname' || key === 'formfirstname' || key === 'editfirstname' || key === 'firstName') {
      if (!isName(value)) message = 'First name must be 2-40 letters only.';
    }

    if (key === 'last_name' || key === 'lastname' || key === 'formlastname' || key === 'editlastname' || key === 'lastName') {
      if (!isName(value)) message = 'Last name must be 2-40 letters only.';
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
          if (!silent) {
            const currentPrefix = `+${phoneDigits.slice(0, countryPrefixDigits.length)}`;
            warnPrefixMismatchOnce(field, countryInfo, form, currentPrefix);
            customAlertHandled = true;
          }
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
      field.dataset.invalid = '1';
      field.style.borderColor = 'rgba(225, 29, 72, 0.55)';
      if (!silent && !customAlertHandled) {
        pushAlert(`${message}`, key);
      }
      return false;
    }

    field.dataset.invalid = '0';
    field.style.borderColor = '';
    return true;
  };

  const validateForm = (form, context = 'generic') => {
    if (!form) return true;

    const fields = Array.from(form.querySelectorAll('input, select, textarea')).filter((field) => {
      const key = fieldKey(field);
      return key !== '' && key !== 'remember_me' && field.type !== 'hidden' && field.type !== 'button' && field.type !== 'submit';
    });

    for (const field of fields) {
      if (!validateField(field, context, false)) {
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
      const run = () => validateField(field, context, false);
      field.addEventListener('blur', run);
      field.addEventListener('change', run);
    });
  };

  const pushAlert = (message, key = '') => {
    const currentKey = `${key}::${message}`;
    if (state.lastAlertKey === currentKey) return;
    state.lastAlertKey = currentKey;

    if (window.Swal) {
      window.Swal.fire({
        toast: true,
        icon: 'error',
        title: message,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3200,
        timerProgressBar: true,
        background: '#0f172a',
        color: '#f8fafc',
        customClass: { container: 'uf-swal-front' },
      });
      return;
    }

    const fallback = document.createElement('div');
    fallback.textContent = message;
    fallback.style.cssText = [
      'position:fixed',
      'top:14px',
      'right:14px',
      'z-index:12000',
      'background:#111827',
      'color:#f8fafc',
      'border:1px solid rgba(239,68,68,0.45)',
      'padding:10px 12px',
      'border-radius:12px',
      'font-size:12px',
      'box-shadow:0 10px 26px rgba(15,23,42,0.35)',
    ].join(';');

    document.body.appendChild(fallback);
    setTimeout(() => fallback.remove(), 2600);
  };

  const resolveCountryName = (address = {}) => {
    const direct = String(
      address.country ||
      address.country_name ||
      address['ISO3166-2-lvl4'] ||
      ''
    ).trim();

    if (direct && !/unknown/i.test(direct)) {
      return direct;
    }

    const cc = String(address.country_code || '').trim().toUpperCase();
    if (cc) {
      try {
        const formatter = new Intl.DisplayNames(['en'], { type: 'region' });
        const mapped = formatter.of(cc);
        if (mapped && !/unknown/i.test(mapped)) {
          return mapped;
        }
      } catch (_error) {
      }
    }

    const fallback = String(address.state || address.county || address.city || address.town || address.village || '').trim();
    return fallback || '';
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
      pushAlert('Please choose a location on the map first.', 'country');
      return;
    }

    refs.countryInput.value = state.selectedLocation.country;
    refs.countryInput.dispatchEvent(new Event('change', { bubbles: true }));
    if (refs.latitudeInput) refs.latitudeInput.value = String(state.selectedLocation.lat);
    if (refs.longitudeInput) refs.longitudeInput.value = String(state.selectedLocation.lng);
    if (refs.cityInput) refs.cityInput.value = state.selectedLocation.city || '';
    if (refs.fullAddressInput) refs.fullAddressInput.value = state.selectedLocation.fullAddress || state.selectedLocation.country;

    validateField(refs.countryInput, 'auth-register', true);
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

    const syncCountryAndPhone = async (countryInput, shouldWarn = true) => {
      const countryValue = String(countryInput?.value || '').trim();
      let info = resolveCountryPhoneInfo(countryValue);
      if (!info && countryValue) {
        setCountryMeta(countryInput, null, true);
        info = await resolveCountryPhoneInfoAsync(countryValue);
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
        warnPrefixMismatchOnce(phoneInput, info, countryInput.closest('form'), currentPrefix);
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
        warnPrefixMismatchOnce(phoneInput, info, phoneInput.closest('form'), currentPrefix);
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
      });
      phoneInput.addEventListener('blur', () => {
        const countryInput = relatedCountryInput(phoneInput);
        markCountryPhoneInteracted(countryInput, phoneInput);
        warnIfPhoneMismatch(phoneInput);
      });
      phoneInput.addEventListener('change', () => {
        const countryInput = relatedCountryInput(phoneInput);
        markCountryPhoneInteracted(countryInput, phoneInput);
        warnIfPhoneMismatch(phoneInput);
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
