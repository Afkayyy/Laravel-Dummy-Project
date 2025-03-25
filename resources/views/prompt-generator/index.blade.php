<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Property Description Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .description-container {
            max-height: 70vh;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-center text-blue-800 mb-8">Property Description Generator</h1>
        
        <div class="flex flex-col md:flex-row gap-8">
            <!-- Left Column - Form -->
            <div class="w-full md:w-1/2 bg-white rounded-lg shadow-md p-6">
                <form id="propertyForm">
                    @csrf
                    
                    <div class="mb-6">
                        <label for="location" class="block text-gray-700 font-medium mb-2">Location</label>
                        <input type="text" id="location" name="location" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Start typing a U.S. location..." autocomplete="off">
                        <input type="hidden" id="latitude" name="latitude">
                        <input type="hidden" id="longitude" name="longitude">
                        <div id="locationSuggestions" class="hidden mt-1 border border-gray-300 rounded-md bg-white shadow-lg z-10 max-h-60 overflow-auto"></div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="rooms" class="block text-gray-700 font-medium mb-2">Number of Rooms</label>
                            <select id="rooms" name="rooms" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @for($i = 1; $i <= 10; $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        
                        <div>
                            <label for="washrooms" class="block text-gray-700 font-medium mb-2">Number of Washrooms</label>
                            <select id="washrooms" name="washrooms" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @for($i = 1; $i <= 10; $i++)
                                    <option value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="description" class="block text-gray-700 font-medium mb-2">Property Features & Amenities</label>
                        <textarea id="description" name="description" rows="4" 
                                  class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Pool, near downtown, modern design, spacious kitchen, etc."></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <label for="platform" class="block text-gray-700 font-medium mb-2">Platform</label>
                        <select id="platform" name="platform" class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="Facebook">Facebook</option>
                            <option value="Instagram">Instagram</option>
                            <option value="Own Website">Own Website</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="w-full bg-blue-600 text-white font-medium py-2 px-4 rounded-md hover:bg-blue-700 transition duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Generate Description
                    </button>
                </form>
            </div>
            
            <!-- Right Column - Results -->
            <div class="w-full md:w-1/2">
                <div id="resultContainer" class="hidden bg-white rounded-lg shadow-md p-6 h-full flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-gray-800">Generated Description</h2>
                        <button id="copyButton" class="bg-gray-200 text-gray-700 font-medium py-1 px-3 rounded-md hover:bg-gray-300 transition duration-200">
                            Copy to Clipboard
                        </button>
                    </div>
                    <div id="generatedDescription" class="prose max-w-none text-gray-700 description-container flex-grow"></div>
                    
                    <!-- Debug Information -->
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <button id="toggleDebug" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                            Show Debug Information
                        </button>
                        <div id="debugInfo" class="hidden mt-2 text-xs bg-gray-100 p-2 rounded overflow-x-auto">
                            <pre id="debugContent" class="whitespace-pre-wrap"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div id="loadingSpinner" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-lg text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-blue-500 mx-auto mb-4"></div>
                <p class="text-gray-700">Generating your property description...</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const locationInput = document.getElementById('location');
            const latitudeInput = document.getElementById('latitude');
            const longitudeInput = document.getElementById('longitude');
            const suggestionsContainer = document.getElementById('locationSuggestions');
            const propertyForm = document.getElementById('propertyForm');
            const resultContainer = document.getElementById('resultContainer');
            const generatedDescription = document.getElementById('generatedDescription');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const copyButton = document.getElementById('copyButton');
            const toggleDebug = document.getElementById('toggleDebug');
            const debugInfo = document.getElementById('debugInfo');
            const debugContent = document.getElementById('debugContent');
            
            let debounceTimer;
            let debugData = {};
            
            // Location autocomplete
            locationInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                const query = this.value.trim();
                
                if (query.length < 3) {
                    suggestionsContainer.classList.add('hidden');
                    return;
                }
                
                debounceTimer = setTimeout(() => {
                    fetch('/location-autocomplete?query=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            if (data.length > 0) {
                                suggestionsContainer.innerHTML = '';
                                data.forEach(item => {
                                    const suggestion = document.createElement('div');
                                    suggestion.className = 'px-4 py-2 hover:bg-gray-100 cursor-pointer';
                                    suggestion.textContent = item.formatted;
                                    suggestion.addEventListener('click', () => {
                                        locationInput.value = item.formatted;
                                        latitudeInput.value = item.lat;
                                        longitudeInput.value = item.lon;
                                        suggestionsContainer.classList.add('hidden');
                                    });
                                    suggestionsContainer.appendChild(suggestion);
                                });
                                suggestionsContainer.classList.remove('hidden');
                            } else {
                                suggestionsContainer.classList.add('hidden');
                            }
                        });
                }, 300);
            });
            
            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (e.target.id !== 'location') {
                    suggestionsContainer.classList.add('hidden');
                }
            });
            
            // Form submission
            propertyForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate location has coordinates
                if (!latitudeInput.value || !longitudeInput.value) {
                    alert('Please select a valid location from the suggestions');
                    return;
                }
                
                // Prepare form data
                const formData = {
                    location: locationInput.value,
                    latitude: latitudeInput.value,
                    longitude: longitudeInput.value,
                    rooms: document.getElementById('rooms').value,
                    washrooms: document.getElementById('washrooms').value,
                    description: document.getElementById('description').value,
                    platform: document.getElementById('platform').value
                };
                
                // Log form data to console
                console.log('Submitting form with data:', formData);
                debugData.input = formData;
                
                loadingSpinner.classList.remove('hidden');
                resultContainer.classList.add('hidden');
                
                fetch('/generate-prompt', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(formData)
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => { throw err; });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('API response:', data);
                    debugData.response = data;
                    
                    generatedDescription.innerHTML = data.description.replace(/\n/g, '<br>');
                    resultContainer.classList.remove('hidden');
                    
                    // Update debug info
                    debugContent.textContent = JSON.stringify({
                        input: debugData.input,
                        prompt: data.prompt,
                        nearby_places: data.nearby_places,
                        response: {
                            description_length: data.description.length,
                            platform: data.input_data.platform
                        }
                    }, null, 2);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error generating description: ' + (error.error || error.message || 'Please try again later'));
                })
                .finally(() => {
                    loadingSpinner.classList.add('hidden');
                });
            });
            
            // Copy to clipboard
            copyButton.addEventListener('click', function() {
                const range = document.createRange();
                range.selectNode(generatedDescription);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
                document.execCommand('copy');
                window.getSelection().removeAllRanges();
                
                const originalText = copyButton.textContent;
                copyButton.textContent = 'Copied!';
                setTimeout(() => {
                    copyButton.textContent = originalText;
                }, 2000);
            });
            
            // Toggle debug info
            toggleDebug.addEventListener('click', function() {
                debugInfo.classList.toggle('hidden');
                toggleDebug.textContent = debugInfo.classList.contains('hidden') 
                    ? 'Show Debug Information' 
                    : 'Hide Debug Information';
            });
        });
    </script>
</body>
</html>