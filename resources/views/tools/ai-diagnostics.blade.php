<x-admin-layout>
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-100">AI Diagnostics</h2>
        </div>

        {{-- Per-Feature Settings --}}
        @foreach($features as $featureKey => $featureLabel)
            @php $cfg = $featureConfigs[$featureKey]; @endphp
            <div class="bg-gray-800 rounded-lg p-6 mb-6" x-data="featureSettings('{{ $featureKey }}', @js($cfg), @js($providers))">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-100">{{ $featureLabel }}</h3>
                    <div class="flex items-center space-x-2">
                        <span class="text-xs px-2 py-1 rounded-full"
                              :class="apiKeySet ? 'bg-green-900 text-green-300' : 'bg-red-900 text-red-300'"
                              x-text="apiKeySet ? 'API Key: ' + apiKeyMasked : 'No API Key'"></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    {{-- Provider --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Provider</label>
                        <select x-model="provider" @change="onProviderChange()"
                                class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white text-sm focus:border-blue-500 focus:outline-none">
                            @foreach($providers as $provKey => $prov)
                                <option value="{{ $provKey }}">{{ $prov['label'] }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1" x-text="providerDescription"></p>
                    </div>

                    {{-- Model --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Model</label>
                        <div class="flex space-x-2">
                            <select x-model="model"
                                    class="flex-1 px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white text-sm focus:border-blue-500 focus:outline-none">
                                <template x-for="m in suggestedModels" :key="m">
                                    <option :value="m" x-text="m"></option>
                                </template>
                            </select>
                            <input type="text" x-model="model" placeholder="or type custom..."
                                   class="w-40 px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white text-sm focus:border-blue-500 focus:outline-none font-mono">
                        </div>
                    </div>

                    {{-- OCR Chat Model (only for mistral-ocr) --}}
                    <div x-show="provider === 'mistral-ocr'">
                        <label class="block text-sm font-medium text-gray-400 mb-1">OCR Chat Model</label>
                        <input type="text" x-model="ocrChatModel"
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white text-sm font-mono focus:border-blue-500 focus:outline-none"
                               placeholder="mistral-small-latest">
                        <p class="text-xs text-gray-500 mt-1">Chat model used to structure OCR text into JSON</p>
                    </div>

                    {{-- Base URL --}}
                    <div x-show="provider !== 'gemini'">
                        <label class="block text-sm font-medium text-gray-400 mb-1">Base URL</label>
                        <input type="text" x-model="baseUrl"
                               class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white text-sm font-mono focus:border-blue-500 focus:outline-none"
                               placeholder="https://api.mistral.ai/v1">
                    </div>

                    {{-- Timeout --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Timeout (seconds)</label>
                        <input type="number" x-model="timeout" min="10" max="300"
                               class="w-24 px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white text-sm focus:border-blue-500 focus:outline-none">
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center space-x-3">
                    <button @click="saveSettings()"
                            :disabled="saving"
                            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-600 text-white text-sm font-medium rounded transition-colors">
                        <svg x-show="!saving" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <svg x-show="saving" class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Save Settings
                    </button>

                    {{-- Save result message --}}
                    <span x-show="saveMessage" class="text-sm"
                          :class="saveSuccess ? 'text-green-400' : 'text-red-400'"
                          x-text="saveMessage"
                          x-transition></span>
                </div>
            </div>
        @endforeach

        {{-- Connection Tests --}}
        <div class="bg-gray-800 rounded-lg p-6 mb-6" x-data="aiTester()">
            <h3 class="text-lg font-semibold text-gray-100 mb-4">Connection Tests</h3>

            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-400 mb-1">Test Feature</label>
                <select x-model="testFeature" class="px-3 py-2 bg-gray-700 border border-gray-600 rounded text-white text-sm focus:border-blue-500 focus:outline-none">
                    @foreach($features as $fk => $fl)
                        <option value="{{ $fk }}">{{ $fl }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-wrap gap-3 mb-4">
                <button @click="runTest('text')"
                        :disabled="testing"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white text-sm font-medium rounded transition-colors">
                    <svg x-show="!testing || testType !== 'text'" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <svg x-show="testing && testType === 'text'" class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Test Text
                </button>

                <button @click="runTest('vision')"
                        :disabled="testing"
                        class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white text-sm font-medium rounded transition-colors">
                    <svg x-show="!testing || testType !== 'vision'" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <svg x-show="testing && testType === 'vision'" class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Test Vision
                </button>

                <button @click="runTest('ocr')"
                        :disabled="testing"
                        class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white text-sm font-medium rounded transition-colors">
                    <svg x-show="!testing || testType !== 'ocr'" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <svg x-show="testing && testType === 'ocr'" class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Test OCR
                </button>
            </div>

            {{-- Test Result --}}
            <div x-show="result !== null" class="mt-4">
                <div x-show="result && result.success" class="p-4 bg-green-900/30 border border-green-600 rounded-lg">
                    <div class="flex items-center mb-3">
                        <span class="text-green-400 text-lg mr-2">&#10003;</span>
                        <span class="text-green-300 font-semibold">Connection Successful</span>
                        <span class="ml-auto text-gray-400 text-sm" x-text="result.duration_ms + 'ms'"></span>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div>
                            <span class="text-gray-400">Model:</span>
                            <span class="text-gray-200 font-mono ml-2" x-text="result.model"></span>
                        </div>
                        <div>
                            <span class="text-gray-400">Response:</span>
                            <span class="text-gray-200 ml-2" x-text="result.response"></span>
                        </div>
                        <div x-show="result.usage">
                            <span class="text-gray-400">Tokens:</span>
                            <span class="text-gray-200 ml-2">
                                <span x-text="result.usage?.prompt_tokens"></span> prompt /
                                <span x-text="result.usage?.completion_tokens"></span> completion
                            </span>
                        </div>
                    </div>
                </div>

                <div x-show="result && !result.success" class="p-4 bg-red-900/30 border border-red-600 rounded-lg">
                    <div class="flex items-center mb-3">
                        <span class="text-red-400 text-lg mr-2">&#10005;</span>
                        <span class="text-red-300 font-semibold">Connection Failed</span>
                        <span class="ml-auto text-gray-400 text-sm" x-text="result.duration_ms + 'ms'"></span>
                    </div>
                    <div class="text-sm">
                        <span class="text-gray-400">Error:</span>
                        <span class="text-red-300 ml-2" x-text="result.error"></span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent AI Errors --}}
        <div class="bg-gray-800 rounded-lg p-6">
            <h3 class="text-lg font-semibold text-gray-100 mb-4">Recent AI Errors</h3>
            @if(count($recentErrors) > 0)
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @foreach($recentErrors as $error)
                    <div class="p-3 bg-gray-700 rounded text-xs font-mono">
                        <span class="text-gray-400">{{ $error['timestamp'] }}</span>
                        <p class="text-red-300 mt-1 break-all">{{ $error['message'] }}</p>
                    </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-400 text-sm">No recent AI-related errors found in logs.</p>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function featureSettings(feature, config, providers) {
            return {
                feature,
                provider: config.provider,
                model: config.model,
                ocrChatModel: config.ocr_chat_model || '',
                baseUrl: config.base_url || '',
                timeout: config.timeout || 120,
                apiKeySet: config.api_key_set,
                apiKeyMasked: config.api_key_masked || '',
                saving: false,
                saveMessage: '',
                saveSuccess: false,
                providers,

                get providerDescription() {
                    return this.providers[this.provider]?.description || '';
                },

                get suggestedModels() {
                    return this.providers[this.provider]?.models || [];
                },

                onProviderChange() {
                    const models = this.suggestedModels;
                    if (models.length > 0 && !models.includes(this.model)) {
                        this.model = models[0];
                    }
                    // Update base URL based on provider
                    if (this.provider.startsWith('mistral')) {
                        this.baseUrl = 'https://api.mistral.ai/v1';
                    } else if (this.provider === 'openai') {
                        this.baseUrl = 'https://api.openai.com/v1';
                    }
                },

                async saveSettings() {
                    this.saving = true;
                    this.saveMessage = '';

                    try {
                        const response = await fetch('{{ route("tools.ai-diagnostics.settings") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                feature: this.feature,
                                provider: this.provider,
                                model: this.model,
                                ocr_chat_model: this.ocrChatModel || null,
                                base_url: this.baseUrl || null,
                                timeout: this.timeout || null,
                            }),
                        });

                        const data = await response.json();

                        if (data.success) {
                            this.saveSuccess = true;
                            this.saveMessage = data.message;
                        } else {
                            this.saveSuccess = false;
                            this.saveMessage = data.message || 'Failed to save';
                        }
                    } catch (e) {
                        this.saveSuccess = false;
                        this.saveMessage = 'Error: ' + e.message;
                    } finally {
                        this.saving = false;
                        setTimeout(() => this.saveMessage = '', 5000);
                    }
                },
            }
        }

        function aiTester() {
            return {
                testing: false,
                testType: null,
                testFeature: 'invoice_parsing',
                result: null,

                async runTest(type) {
                    this.testing = true;
                    this.testType = type;
                    this.result = null;

                    try {
                        const response = await fetch('{{ route("tools.ai-diagnostics.test") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ test_type: type, feature: this.testFeature }),
                        });

                        this.result = await response.json();
                    } catch (e) {
                        this.result = {
                            success: false,
                            error: 'Network error: ' + e.message,
                            duration_ms: 0,
                        };
                    } finally {
                        this.testing = false;
                    }
                },
            }
        }
    </script>
    @endpush
</x-admin-layout>
