import * as THREE from "three";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";
import { GUI } from "https://cdn.skypack.dev/lil-gui@0.17.0";

const containerEl = document.querySelector(".globe-wrapper");
const canvasEl = containerEl.querySelector("#globe-3d");
const svgMapDomEl = document.querySelector("#map");
const svgCountries = Array.from(svgMapDomEl.querySelectorAll("path"));
const svgCountryDomEl = document.querySelector("#country");
const countryNameEl = document.querySelector(".info span");

let renderer, scene, camera, rayCaster, pointer, controls;
let globeGroup, globeColorMesh, globeStrokesMesh, globeSelectionOuterMesh;

const svgViewBox = [2000, 1000];
const offsetY = -.1;

const params = {
    strokeColor: "#111111",
    defaultColor: "#9a9591",
    hoverColor: "#00C9A2",
    fogColor: "#e4e5e6",
    fogDistance: 2.6,
    strokeWidth: 2,
    hiResScalingFactor: 2,
    lowResScalingFactor: .7
}

// Auto-rotation configuration
let autoRotation = false;

let hoveredCountryIdx = 6;
let isTouchScreen = false;
let isHoverable = true;
let isPanelOpen = false; // Track if the country panel is open

const textureLoader = new THREE.TextureLoader();
let staticMapUri;
const bBoxes = [];
const dataUris = [];


initScene();
createControls();

window.addEventListener("resize", updateSize);

// Country name normalization function
function normalizeCountryName(name) {
    if (!name) return name;

    // Common abbreviation mappings
    const mappings = {
        'uk': 'United Kingdom',
        'u.k.': 'United Kingdom',
        'britain': 'United Kingdom',
        'great britain': 'United Kingdom',
        'england': 'United Kingdom',

        'usa': 'United States',
        'u.s.a.': 'United States',
        'us': 'United States',
        'u.s.': 'United States',
        'america': 'United States',

        'uae': 'United Arab Emirates',
        'u.a.e.': 'United Arab Emirates',
        'u. a. e.': 'United Arab Emirates',

        'russia': 'Russian Federation',
        'ussr': 'Russian Federation',
        'russian federation': 'Russia',

        'czech republic': 'Czechia',
        'czechia': 'Czech Republic',

        'macedonia': 'North Macedonia',
        'north macedonia': 'Macedonia',
        'fyrom': 'North Macedonia',

        'bahamas': 'The Bahamas',
        'the bahamas': 'Bahamas',

        'gambia': 'The Gambia',
        'the gambia': 'Gambia',

        'congo, democratic republic': 'Democratic Republic of the Congo',
        'democratic republic of the congo': 'Democratic Republic of the Congo',
        'drc': 'Democratic Republic of the Congo',
        'congo-kinshasa': 'Democratic Republic of the Congo',

        'congo, republic': 'Republic of the Congo',
        'republic of the congo': 'Republic of the Congo',
        'congo-brazzaville': 'Republic of the Congo',
        'republic of congo': 'Republic of the Congo',

        'ivory coast': 'Côte d\'Ivoire',
        'côte d\'ivoire': 'Ivory Coast',
        'cote d\'ivoire': 'Ivory Coast',

        'south korea': 'South Korea',
        'korea, republic of': 'South Korea',
        'republic of korea': 'South Korea',

        'north korea': 'North Korea',
        'korea, democratic people\'s republic of': 'North Korea',
        'democratic people\'s republic of korea': 'North Korea',

        'taiwan': 'Taiwan, Province of China',
        'taiwan, province of china': 'Taiwan',

        'laos': 'Lao People\'s Democratic Republic',
        'lao people\'s democratic republic': 'Laos',
        'lao pdr': 'Laos',

        'burma': 'Myanmar',
        'myanmar': 'Burma',

        'swaziland': 'Eswatini',
        'eswatini': 'Swaziland',
        'kingdom of eswatini': 'Eswatini',

        'netherlands': 'Netherlands',
        'the netherlands': 'Netherlands',
        'holland': 'Netherlands',

        'moldova': 'Moldova, Republic of',
        'moldova, republic of': 'Moldova',

        'syria': 'Syrian Arab Republic',
        'syrian arab republic': 'Syria',

        'iran': 'Iran, Islamic Republic of',
        'iran, islamic republic of': 'Iran',
        'islamic republic of iran': 'Iran',

        'vietnam': 'Viet Nam',
        'viet nam': 'Vietnam',

        'tanzania': 'Tanzania, United Republic of',
        'tanzania, united republic of': 'Tanzania',
        'united republic of tanzania': 'Tanzania',

        'venezuela': 'Venezuela, Bolivarian Republic of',
        'venezuela, bolivarian republic of': 'Venezuela',
        'bolivarian republic of venezuela': 'Venezuela',

        'bolivia': 'Bolivia, Plurinational State of',
        'bolivia, plurinational state of': 'Bolivia',
        'plurinational state of bolivia': 'Bolivia',

        'brunei': 'Brunei Darussalam',
        'brunei darussalam': 'Brunei',

        'cape verde': 'Cabo Verde',
        'cabo verde': 'Cape Verde',

        'east timor': 'Timor-Leste',
        'timor-leste': 'East Timor',

        'macedonia': 'North Macedonia',
        'north macedonia': 'Macedonia',
        'the former yugoslav republic of macedonia': 'North Macedonia',
        'fyrom': 'North Macedonia',

        'macao': 'Macao SAR',
        'macao sar': 'Macao',
        'macau': 'Macao',

        'reunion': 'Réunion',
        'réunion': 'Reunion',

        // Additional mappings from the globe SVG country codes
        'c?te d\'ivoire': 'Côte d\'Ivoire',
        'faeroe islands': 'Faroe Islands',
        'falkland islands': 'Falkland Islands (Malvinas)',
        'virgin islands, british': 'British Virgin Islands',
        'virgin islands, u.s.': 'U.S. Virgin Islands',
        'saint kitts and nevis': 'St. Kitts and Nevis',
        'saint vincent and the grenadines': 'St. Vincent and the Grenadines',
        'saint lucia': 'St. Lucia',
        's?o tom? and pr?ncipe': 'São Tomé and Príncipe',
        's?o tom? and pr?ncipe': 'Sao Tome and Principe',
    };

    const cleanName = name.trim().toLowerCase();

    // Check if the name exists in our mappings
    if (mappings[cleanName]) {
        return mappings[cleanName];
    }

    // If not found in mappings, apply proper case formatting
    return name.replace(/\w\S*/g, function (txt) {
        return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
    });
}

function emitCountrySelection(countryName) {
    const payload = {
        country: countryName,
        fullAddress: countryName,
        source: '3d-globe'
    };

    try {
        if (window.opener && !window.opener.closed) {
            window.opener.postMessage({ type: 'globale-explore-select', payload }, '*');
        }
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'globale-explore-select', payload }, '*');
        }
    } catch (error) {
        console.error('Failed to emit 3D globe country selection:', error);
    }
}

containerEl.addEventListener("touchstart", (e) => {
    isTouchScreen = true;
});
containerEl.addEventListener("mousemove", (e) => {
    updateMousePosition(e.clientX, e.clientY);
});
containerEl.addEventListener("click", (e) => {
    updateMousePosition(e.clientX, e.clientY);
    handleCountryClick();
});

// Add Missing updateMousePosition Function - account for container offset
function updateMousePosition(x, y) {
    const rect = containerEl.getBoundingClientRect();
    pointer.x = ((x - rect.left) / rect.width) * 2 - 1;
    pointer.y = -((y - rect.top) / rect.height) * 2 + 1;
}



// Handle country click to show panel with actions/resources
function handleCountryClick() {
    // This function is safe to run after the existing functionality
    if (typeof isHoverable !== 'undefined' && typeof hoveredCountryIdx !== 'undefined' &&
        isHoverable && hoveredCountryIdx !== -1) {
        let countryName = svgCountries[hoveredCountryIdx].getAttribute("data-name");
        if (countryName) {
            // Clean up country name to handle potential formatting differences
            countryName = countryName.trim();

            // Normalize the country name using comprehensive mappings
            countryName = normalizeCountryName(countryName);

            emitCountrySelection(countryName);

            // Temporarily disable auto-rotation when clicking a country

            // Temporarily disable auto-rotation when clicking a country
            if (controls) {
                controls.autoRotate = false;
            }
            console.log('Normalized country name:', countryName);
            loadCountryData(countryName);
        }
    }
}

// Load data for the selected country
async function loadCountryData(country) {
    try {
        console.log('Loading data for country:', country); // Debug log

        // Show loading state
        showCountryPanel();
        showLoadingState();

        // Fetch data from both APIs
        const [actionsResponse, resourcesResponse] = await Promise.allSettled([
            fetch(`../../api/actions/get_actions_by_country.php?country=${encodeURIComponent(country)}`),
            fetch(`../../api/resources/get_resources_by_country.php?country=${encodeURIComponent(country)}`)
        ]);

        // Check each response for errors
        let actionsData = { success: false, actions: [], message: 'No response', debug: {} };
        let resourcesData = { success: false, resources: [], message: 'No response', debug: {} };

        if (actionsResponse.status === 'fulfilled') {
            actionsData = await actionsResponse.value.json();
            console.log('Actions response:', actionsData); // Debug log
        } else {
            console.error('Actions API error:', actionsResponse.reason);
        }

        if (resourcesResponse.status === 'fulfilled') {
            resourcesData = await resourcesResponse.value.json();
            console.log('Resources response:', resourcesData); // Debug log
        } else {
            console.error('Resources API error:', resourcesResponse.reason);
        }

        // Check for success before processing
        const actions = actionsData.success ? actionsData.actions : [];
        const resources = resourcesData.success ? resourcesData.resources : [];

        console.log(`Loaded ${actions.length} actions and ${resources.length} resources for ${country}`); // Debug log

        // Render the data
        renderCountryData(country, actions, resources);
    } catch (error) {
        console.error('Error loading country data:', error);
        showErrorMessage(`Failed to load data for "${country}". Error: ${error.message}`);
    }
}

// Show the right-side panel
function showCountryPanel() {
    // Check if our panel already exists
    let panel = document.getElementById('countryPanel');
    if (panel) {
        panel.classList.remove('hidden');
    } else {
        // Create the panel if it doesn't exist
        createCountryPanel();
        // Now that the panel is created, retrieve it and remove the hidden class
        panel = document.getElementById('countryPanel');
        if (panel) {
            panel.classList.remove('hidden');
        }
    }

    // Update the panel state
    isPanelOpen = true;

    // Temporarily disable auto-rotation when panel is open
    if (controls) {
        controls.autoRotate = true;
    }
}

// Create the country panel dynamically
function createCountryPanel() {
    const panel = document.createElement('div');
    panel.id = 'countryPanel';
    panel.className = 'country-panel hidden';
    panel.innerHTML = `
        <div class="panel-header">
            <h2 id="panelCountryName">Country Name</h2>
            <button class="panel-close-btn" onclick="closeCountryPanel()">×</button>
        </div>
        <div class="panel-controls">
            <input type="text" id="searchInput" placeholder="Search actions/resources..." class="search-input">
            <select id="filterSelect" class="filter-select">
                <option value="all">All Types</option>
                <option value="action">Actions Only</option>
                <option value="resource">Resources Only</option>
            </select>
            <select id="sortBy" class="sort-select">
                <option value="date_desc">Date (Newest)</option>
                <option value="date_asc">Date (Oldest)</option>
                <option value="name">Name (A-Z)</option>
                <option value="category">Category</option>
            </select>
        </div>
        <div class="panel-stats">
            <div class="stat-item">
                <span class="stat-number" id="actionsCount">0</span>
                <span class="stat-label">Actions</span>
            </div>
            <div class="stat-item">
                <span class="stat-number" id="resourcesCount">0</span>
                <span class="stat-label">Resources</span>
            </div>
        </div>
        <div class="panel-content">
            <div class="section-header">Actions</div>
            <div id="actionsList" class="items-list"></div>
            <div class="section-header">Resources</div>
            <div id="resourcesList" class="items-list"></div>
        </div>
    `;
    document.body.appendChild(panel);

    // Add event listeners for search and filter
    setupPanelControls();
}

// Setup search and filter controls
function setupPanelControls() {
    const searchInput = document.getElementById('searchInput');
    const filterSelect = document.getElementById('filterSelect');
    const sortBy = document.getElementById('sortBy');

    if (searchInput) {
        searchInput.addEventListener('input', debounce(filterAndSortItems, 300));
    }

    if (filterSelect) {
        filterSelect.addEventListener('change', filterAndSortItems);
    }

    if (sortBy) {
        sortBy.addEventListener('change', filterAndSortItems);
    }
}

// Debounce function to limit search calls
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Apply filtering and sorting to the items
function filterAndSortItems() {
    const searchTerm = (document.getElementById('searchInput')?.value || '').toLowerCase();
    const filterValue = document.getElementById('filterSelect')?.value || 'all';
    const sortValue = document.getElementById('sortBy')?.value || 'date_desc';

    // Apply filters and sorting to the currently stored data
    applyFiltersAndSorting(searchTerm, filterValue, sortValue);
}

// Global variables to store the current data
let currentCountry = '';
let currentActions = [];
let currentResources = [];

// Apply filtering and sorting to existing data
function applyFiltersAndSorting(searchTerm = '', filterValue = 'all', sortValue = 'date_desc') {
    // Filter actions
    let filteredActions = [...currentActions];
    if (searchTerm) {
        filteredActions = filteredActions.filter(action =>
            action.title.toLowerCase().includes(searchTerm) ||
            (action.location && action.location.toLowerCase().includes(searchTerm)) ||
            (action.category && action.category.toLowerCase().includes(searchTerm))
        );
    }

    // Filter resources
    let filteredResources = [...currentResources];
    if (searchTerm) {
        filteredResources = filteredResources.filter(resource =>
            resource.resource_name.toLowerCase().includes(searchTerm) ||
            (resource.location && resource.location.toLowerCase().includes(searchTerm)) ||
            (resource.category && resource.category.toLowerCase().includes(searchTerm)) ||
            (resource.type && resource.type.toLowerCase().includes(searchTerm))
        );
    }

    // Apply type filter
    if (filterValue === 'action') {
        filteredResources = [];
    } else if (filterValue === 'resource') {
        filteredActions = [];
    }

    // Apply sorting
    filteredActions = sortItems(filteredActions, sortValue, 'action');
    filteredResources = sortItems(filteredResources, sortValue, 'resource');

    // Render the filtered and sorted results
    renderFilteredData(currentCountry, filteredActions, filteredResources);
}

// Sort items based on the selected sort option
function sortItems(items, sortValue, type) {
    return items.sort((a, b) => {
        switch (sortValue) {
            case 'date_desc':
                // Sort by date descending (newest first)
                const dateA = type === 'action' && a.start_time ? new Date(a.start_time) :
                    type === 'resource' && a.created_at ? new Date(a.created_at) :
                        new Date(0);
                const dateB = type === 'action' && b.start_time ? new Date(b.start_time) :
                    type === 'resource' && b.created_at ? new Date(b.created_at) :
                        new Date(0);
                return dateB - dateA;

            case 'date_asc':
                // Sort by date ascending (oldest first)
                const dateA1 = type === 'action' && a.start_time ? new Date(a.start_time) :
                    type === 'resource' && a.created_at ? new Date(a.created_at) :
                        new Date(0);
                const dateB1 = type === 'action' && b.start_time ? new Date(b.start_time) :
                    type === 'resource' && b.created_at ? new Date(b.created_at) :
                        new Date(0);
                return dateA1 - dateB1;

            case 'name':
                // Sort by name alphabetically
                const nameA = type === 'action' ? a.title || '' : a.resource_name || '';
                const nameB = type === 'action' ? b.title || '' : b.resource_name || '';
                return nameA.localeCompare(nameB);

            case 'category':
                // Sort by category
                const catA = a.category || '';
                const catB = b.category || '';
                return catA.localeCompare(catB);

            default:
                return 0;
        }
    });
}

// Render filtered and sorted data
function renderFilteredData(country, actions, resources) {
    // Update statistics
    document.getElementById('actionsCount').textContent = actions.length;
    document.getElementById('resourcesCount').textContent = resources.length;

    // Render actions list
    const actionsList = document.getElementById('actionsList');
    if (actions.length > 0) {
        actionsList.innerHTML = '';
        actions.forEach(action => {
            // Determine if the item has coordinates
            const hasCoordinates = !!(action.latitude && action.longitude);
            const locationClass = hasCoordinates ? 'location has-coordinates' : 'location no-coordinates';

            // Format location display
            let locationDisplay = action.location || 'Location not specified';
            if (action.location_details && action.country) {
                locationDisplay = `${action.location_details}, ${action.country}`;
            } else if (action.location_details && !action.country) {
                locationDisplay = action.location_details;
            } else if (!action.location_details && action.country) {
                locationDisplay = action.country;
            }

            const itemCard = document.createElement('div');
            itemCard.className = `item-card ${hasCoordinates ? 'has-coordinates' : 'no-coordinates'}`;
            itemCard.innerHTML = `
                <h3>${action.title}</h3>
                <div class="${locationClass}">📍 ${locationDisplay}</div>
                <div class="category">🏷️ ${action.category || 'Uncategorized'}</div>
                ${action.start_time ? `<div class="date">📅 ${new Date(action.start_time).toLocaleDateString()}</div>` : ''}
                <div class="type-badge action-badge">Action</div>
                ${action.participants ? `<div class="participants">👥 ${action.participants} participants</div>` : ''}
                ${!hasCoordinates ? '<div class="location-indicator">📍 Location details only (coordinates not available)</div>' : ''}
            `;
            itemCard.setAttribute('data-type', 'action');
            itemCard.setAttribute('data-latitude', action.latitude || '');
            itemCard.setAttribute('data-longitude', action.longitude || '');
            itemCard.addEventListener('click', () => navigateToMap(action, 'action'));
            actionsList.appendChild(itemCard);
        });
    } else {
        actionsList.innerHTML = '<div class="no-items">No actions found</div>';
    }

    // Render resources list
    const resourcesList = document.getElementById('resourcesList');
    if (resources.length > 0) {
        resourcesList.innerHTML = '';
        resources.forEach(resource => {
            // Determine if the item has coordinates
            const hasCoordinates = !!(resource.latitude && resource.longitude);
            const locationClass = hasCoordinates ? 'location has-coordinates' : 'location no-coordinates';

            // Format location display
            let locationDisplay = resource.location || 'Location not specified';
            if (resource.location_details && resource.country) {
                locationDisplay = `${resource.location_details}, ${resource.country}`;
            } else if (resource.location_details && !resource.country) {
                locationDisplay = resource.location_details;
            } else if (!resource.location_details && resource.country) {
                locationDisplay = resource.country;
            }

            const itemCard = document.createElement('div');
            itemCard.className = `item-card ${hasCoordinates ? 'has-coordinates' : 'no-coordinates'}`;
            itemCard.innerHTML = `
                <h3>${resource.resource_name}</h3>
                <div class="${locationClass}">📍 ${locationDisplay}</div>
                <div class="category">🏷️ ${resource.type} - ${resource.category}</div>
                ${resource.created_at ? `<div class="date">📅 ${new Date(resource.created_at).toLocaleDateString()}</div>` : ''}
                <div class="type-badge resource-badge">Resource</div>
                ${resource.comment_count ? `<div class="comments">💬 ${resource.comment_count} comments</div>` : ''}
                ${!hasCoordinates ? '<div class="location-indicator">📍 Location details only (coordinates not available)</div>' : ''}
            `;
            itemCard.setAttribute('data-type', 'resource');
            itemCard.setAttribute('data-latitude', resource.latitude || '');
            itemCard.setAttribute('data-longitude', resource.longitude || '');
            itemCard.addEventListener('click', () => navigateToMap(resource, 'resource'));
            resourcesList.appendChild(itemCard);
        });
    } else {
        resourcesList.innerHTML = '<div class="no-items">No resources found</div>';
    }
}

// Show loading state in the panel
function showLoadingState() {
    document.getElementById('actionsList').innerHTML = '<div class="loading">Loading actions...</div>';
    document.getElementById('resourcesList').innerHTML = '<div class="loading">Loading resources...</div>';
}

// Show error message in the panel
function showErrorMessage(message) {
    document.getElementById('actionsList').innerHTML = `<div class="error">${message}</div>`;
    document.getElementById('resourcesList').innerHTML = `<div class="error">${message}</div>`;
}

// Render country data in the panel
function renderCountryData(country, actions, resources) {
    // Store the data in global variables for filtering/sorting
    currentCountry = country;
    currentActions = [...actions];  // Create a copy to avoid reference issues
    currentResources = [...resources];

    // Update panel header
    document.getElementById('panelCountryName').textContent = country;

    // Update statistics
    document.getElementById('actionsCount').textContent = actions.length;
    document.getElementById('resourcesCount').textContent = resources.length;

    // Load and display country statistics
    loadCountryStatistics(country);

    // Render actions list
    const actionsList = document.getElementById('actionsList');
    if (actions.length > 0) {
        actionsList.innerHTML = '';
        actions.forEach(action => {
            // Determine if the item has coordinates
            const hasCoordinates = !!(action.latitude && action.longitude);
            const locationClass = hasCoordinates ? 'location has-coordinates' : 'location no-coordinates';

            // Format location display
            let locationDisplay = action.location || 'Location not specified';
            if (action.location_details && action.country) {
                locationDisplay = `${action.location_details}, ${action.country}`;
            } else if (action.location_details && !action.country) {
                locationDisplay = action.location_details;
            } else if (!action.location_details && action.country) {
                locationDisplay = action.country;
            }

            const itemCard = document.createElement('div');
            itemCard.className = `item-card ${hasCoordinates ? 'has-coordinates' : 'no-coordinates'}`;
            itemCard.innerHTML = `
                <h3>${action.title}</h3>
                <div class="${locationClass}">📍 ${locationDisplay}</div>
                <div class="category">🏷️ ${action.category || 'Uncategorized'}</div>
                ${action.start_time ? `<div class="date">📅 ${new Date(action.start_time).toLocaleDateString()}</div>` : ''}
                <div class="type-badge action-badge">Action</div>
                ${action.participants ? `<div class="participants">👥 ${action.participants} participants</div>` : ''}
                ${!hasCoordinates ? '<div class="location-indicator">📍 Location details only (coordinates not available)</div>' : ''}
            `;
            itemCard.setAttribute('data-type', 'action');
            itemCard.setAttribute('data-latitude', action.latitude || '');
            itemCard.setAttribute('data-longitude', action.longitude || '');
            itemCard.addEventListener('click', () => navigateToMap(action, 'action'));
            actionsList.appendChild(itemCard);
        });
    } else {
        actionsList.innerHTML = '<div class="no-items">No actions found</div>';
    }

    // Render resources list
    const resourcesList = document.getElementById('resourcesList');
    if (resources.length > 0) {
        resourcesList.innerHTML = '';
        resources.forEach(resource => {
            // Determine if the item has coordinates
            const hasCoordinates = !!(resource.latitude && resource.longitude);
            const locationClass = hasCoordinates ? 'location has-coordinates' : 'location no-coordinates';

            // Format location display
            let locationDisplay = resource.location || 'Location not specified';
            if (resource.location_details && resource.country) {
                locationDisplay = `${resource.location_details}, ${resource.country}`;
            } else if (resource.location_details && !resource.country) {
                locationDisplay = resource.location_details;
            } else if (!resource.location_details && resource.country) {
                locationDisplay = resource.country;
            }

            const itemCard = document.createElement('div');
            itemCard.className = `item-card ${hasCoordinates ? 'has-coordinates' : 'no-coordinates'}`;
            itemCard.innerHTML = `
                <h3>${resource.resource_name}</h3>
                <div class="${locationClass}">📍 ${locationDisplay}</div>
                <div class="category">🏷️ ${resource.type} - ${resource.category}</div>
                ${resource.created_at ? `<div class="date">📅 ${new Date(resource.created_at).toLocaleDateString()}</div>` : ''}
                <div class="type-badge resource-badge">Resource</div>
                ${resource.comment_count ? `<div class="comments">💬 ${resource.comment_count} comments</div>` : ''}
                ${!hasCoordinates ? '<div class="location-indicator">📍 Location details only (coordinates not available)</div>' : ''}
            `;
            itemCard.setAttribute('data-type', 'resource');
            itemCard.setAttribute('data-latitude', resource.latitude || '');
            itemCard.setAttribute('data-longitude', resource.longitude || '');
            itemCard.addEventListener('click', () => navigateToMap(resource, 'resource'));
            resourcesList.appendChild(itemCard);
        });
    } else {
        resourcesList.innerHTML = '<div class="no-items">No resources found</div>';
    }

    // Set up controls after data is loaded
    setupPanelControls();
}

// Load and display country statistics
async function loadCountryStatistics(country) {
    try {
        const response = await fetch(`../../api/location/get_country_statistics.php?country=${encodeURIComponent(country)}`);
        const data = await response.json();

        if (data.success && data.statistics) {
            const stats = data.statistics;

            // Update main statistics with accurate counts
            document.getElementById('actionsCount').textContent = stats.actions.approved || 0;
            document.getElementById('resourcesCount').textContent = stats.resources.approved || 0;

            console.log('Country statistics loaded:', stats); // Debug log
        } else {
            console.warn('No statistics found for country:', country, data);
        }
    } catch (error) {
        console.error('Error loading country statistics:', error);
        // Continue without detailed statistics if API fails
    }
}

// Close the country panel
function closeCountryPanel() {
    const panel = document.getElementById('countryPanel');
    if (panel) {
        panel.classList.add('hidden');
    }

    // Update panel state
    isPanelOpen = false;

    // Re-enable auto-rotation when panel is closed (only if it should be enabled)
    if (controls && autoRotation) {
        controls.autoRotate = flase;
    }
}

// Navigate to the main map with the selected item's coordinates
function navigateToMap(item, type) {
    // Always navigate to the main page, even if coordinates are missing
    // Set the panel state as open before navigating
    isPanelOpen = true;

    // Update auto-rotation based on panel state
    if (controls) {
        controls.autoRotate = autoRotation && !isPanelOpen;
    }

    // Construct URL with parameters - include coordinates if available
    const params = new URLSearchParams({
        title: item.title || item.resource_name,
        type: type,
        id: item.id,
        country: item.country || currentCountry || '',
        hasCoordinates: !!(item.latitude && item.longitude)  // Add flag if coordinates exist
    });

    // Add coordinates only if they exist
    if (item.latitude && item.longitude) {
        params.append('lat', item.latitude);
        params.append('lng', item.longitude);
    }

    // Show SweetAlert before navigation
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Redirecting...',
            text: `Opening map to view ${type === 'action' ? 'action' : 'resource'} "${item.title || item.resource_name}"`,
            icon: 'info',
            timer: 1500,
            timerProgressBar: true,
            showConfirmButton: false,
            willClose: () => {
                window.location.href = `../aid.html?${params.toString()}`;
            }
        });
    } else {
        // Fallback to navigate without SweetAlert if not available
        window.location.href = `../aid.html?${params.toString()}`;
    }
}

// Expose closeCountryPanel globally to make it accessible to the onclick handler in the HTML
window.closeCountryPanel = closeCountryPanel;

// Function to initialize country highlighting based on activity
async function initCountryActivityHighlights() {
    try {
        const response = await fetch('../../api/location/get_countries_with_data.php');
        const data = await response.json();

        if (data.success && data.countries) {
            // Store country activity data for reference
            window.countryActivityData = data.countries;

            // This could be used to color countries based on activity level
            // For now, we're just storing the data for potential use
        }
    } catch (error) {
        console.error('Error loading country activity data:', error);
    }
}

// Initialize with a small delay to ensure everything is loaded
setTimeout(initCountryActivityHighlights, 1000);



function initScene() {
    renderer = new THREE.WebGLRenderer({ canvas: canvasEl, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

    scene = new THREE.Scene();
    scene.fog = new THREE.Fog(params.fogColor, 0, params.fogDistance);

    camera = new THREE.OrthographicCamera(-1.2, 1.2, 1.2, -1.2, 0, 3);
    camera.position.z = 1.3;

    globeGroup = new THREE.Group();
    scene.add(globeGroup);

    rayCaster = new THREE.Raycaster();
    rayCaster.far = 1.15;
    pointer = new THREE.Vector2(-1, -1);

    createOrbitControls();
    createGlobe();
    prepareHiResTextures();
    prepareLowResTextures();


    updateSize();

    gsap.ticker.add(render);
}


function createOrbitControls() {
    controls = new OrbitControls(camera, canvasEl);
    controls.enablePan = false;
    // controls.enableZoom = false;
    controls.enableDamping = true;
    controls.minPolarAngle = .46 * Math.PI;
    controls.maxPolarAngle = .46 * Math.PI;
    controls.autoRotate = true; // Default to auto-rotate disabled
    controls.autoRotateSpeed *= 1.2;

    controls.addEventListener("start", () => {
        isHoverable = false;
        // Disable auto-rotation when user starts interacting
        controls.autoRotate = false;
        pointer = new THREE.Vector2(-1, -1);
        gsap.to(globeGroup.scale, {
            duration: .3,
            x: .9,
            y: .9,
            z: .9,
            ease: "power1.inOut"
        })
    });
    controls.addEventListener("end", () => {
        // isHoverable = true;
        gsap.to(globeGroup.scale, {
            duration: .6,
            x: 1,
            y: 1,
            z: 1,
            ease: "back(1.7).out",
            onComplete: () => {
                isHoverable = true;
                // Only re-enable auto-rotation if panel is not open
                controls.autoRotate = autoRotation && !isPanelOpen;
            }
        })
    });
}

function createGlobe() {
    const globeGeometry = new THREE.IcosahedronGeometry(1, 20);

    const globeColorMaterial = new THREE.MeshBasicMaterial({
        transparent: true,
        alphaTest: true,
        side: THREE.DoubleSide
    });
    const globeStrokeMaterial = new THREE.MeshBasicMaterial({
        transparent: true,
        depthTest: false,
    });
    const outerSelectionColorMaterial = new THREE.MeshBasicMaterial({
        transparent: true,
        side: THREE.DoubleSide
    });

    globeColorMesh = new THREE.Mesh(globeGeometry, globeColorMaterial);
    globeStrokesMesh = new THREE.Mesh(globeGeometry, globeStrokeMaterial);
    globeSelectionOuterMesh = new THREE.Mesh(globeGeometry, outerSelectionColorMaterial);

    globeStrokesMesh.renderOrder = 2;

    globeGroup.add(globeStrokesMesh, globeSelectionOuterMesh, globeColorMesh);
}

function setMapTexture(material, URI) {
    textureLoader.load(
        URI,
        (t) => {
            t.repeat.set(1, 1);
            material.map = t;
            material.needsUpdate = true;
        });
}

function prepareHiResTextures() {
    let svgData;
    gsap.set(svgMapDomEl, {
        attr: {
            "viewBox": "0 " + (offsetY * svgViewBox[1]) + " " + svgViewBox[0] + " " + svgViewBox[1],
            "stroke-width": params.strokeWidth,
            "stroke": params.strokeColor,
            "fill": params.defaultColor,
            "width": svgViewBox[0] * params.hiResScalingFactor,
            "height": svgViewBox[1] * params.hiResScalingFactor,
        }
    })
    svgData = new XMLSerializer().serializeToString(svgMapDomEl);
    staticMapUri = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svgData);
    setMapTexture(globeColorMesh.material, staticMapUri);

    gsap.set(svgMapDomEl, {
        attr: {
            "fill": "none",
            "stroke": params.strokeColor,
        }
    })
    svgData = new XMLSerializer().serializeToString(svgMapDomEl);
    staticMapUri = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svgData);
    setMapTexture(globeStrokesMesh.material, staticMapUri);
    countryNameEl.innerHTML = svgCountries[hoveredCountryIdx].getAttribute("data-name");

}

function prepareLowResTextures() {
    gsap.set(svgCountryDomEl, {
        attr: {
            "viewBox": "0 " + (offsetY * svgViewBox[1]) + " " + svgViewBox[0] + " " + svgViewBox[1],
            "stroke-width": params.strokeWidth,
            "stroke": params.strokeColor,
            "fill": params.hoverColor,
            "width": svgViewBox[0] * params.lowResScalingFactor,
            "height": svgViewBox[1] * params.lowResScalingFactor,
        }
    })
    svgCountries.forEach((path, idx) => {
        bBoxes[idx] = path.getBBox();
    })
    svgCountries.forEach((path, idx) => {
        svgCountryDomEl.innerHTML = "";
        svgCountryDomEl.appendChild(svgCountries[idx].cloneNode(true));
        const svgData = new XMLSerializer().serializeToString(svgCountryDomEl);
        dataUris[idx] = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svgData);
    })
    setMapTexture(globeSelectionOuterMesh.material, dataUris[hoveredCountryIdx]);

}

function updateMap(uv = { x: 0, y: 0 }) {
    const pointObj = svgMapDomEl.createSVGPoint();
    pointObj.x = uv.x * svgViewBox[0];
    pointObj.y = (1 + offsetY - uv.y) * svgViewBox[1];

    for (let i = 0; i < svgCountries.length; i++) {
        const boundingBox = bBoxes[i];
        if (
            pointObj.x >= boundingBox.x && pointObj.x <= boundingBox.x + boundingBox.width &&
            pointObj.y >= boundingBox.y && pointObj.y <= boundingBox.y + boundingBox.height
        ) {
            const isHovering = svgCountries[i].isPointInFill(pointObj);
            if (isHovering) {
                if (i !== hoveredCountryIdx) {
                    hoveredCountryIdx = i;
                    setMapTexture(globeSelectionOuterMesh.material, dataUris[hoveredCountryIdx]);
                    countryNameEl.innerHTML = svgCountries[hoveredCountryIdx].getAttribute("data-name");
                    break;
                }
            }
        }
    }
}

function render() {
    controls.update();

    if (isHoverable) {
        rayCaster.setFromCamera(pointer, camera);
        const intersects = rayCaster.intersectObject(globeStrokesMesh);
        if (intersects.length) {
            updateMap(intersects[0].uv);
        }
    }

    if (isTouchScreen && isHoverable) {
        isHoverable = false;
    }

    renderer.render(scene, camera);
}

function updateSize() {
    const side = Math.min(500, Math.min(window.innerWidth, window.innerHeight) - 50);
    containerEl.style.width = side + "px";
    containerEl.style.height = side + "px";
    renderer.setSize(side, side);
}


function createControls() {
    const gui = new GUI();

    gui.close();

    gui.addColor(params, "strokeColor")
        .onChange(prepareHiResTextures)
        .name("stroke")
    gui.addColor(params, "defaultColor")
        .onChange(prepareHiResTextures)
        .name("color")
    gui.addColor(params, "hoverColor")
        .onChange(prepareLowResTextures)
        .name("highlight")
    gui.addColor(params, "fogColor")
        .onChange(() => {
            scene.fog = new THREE.Fog(params.fogColor, 0, params.fogDistance);
        })
        .name("fog");
    gui.add(params, "fogDistance", 1, 4)
        .onChange(() => {
            scene.fog = new THREE.Fog(params.fogColor, 0, params.fogDistance);
        })
        .name("fog distance");
}

