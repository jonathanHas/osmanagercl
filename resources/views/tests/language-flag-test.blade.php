<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDEA Language Flag Test - {{ $results['product_code'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .section { background: white; margin: 20px 0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        h1, h2, h3 { margin-top: 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; max-height: 300px; overflow-y: auto; }
        .status-good { color: #28a745; font-weight: bold; }
        .status-bad { color: #dc3545; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px 12px; text-align: left; border: 1px solid #ddd; word-break: break-word; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .language-english { background-color: #d4edda; color: #155724; }
        .language-dutch { background-color: #f8d7da; color: #721c24; }
        .language-mixed { background-color: #fff3cd; color: #856404; }
        .code-snippet { background: #f8f9fa; padding: 10px; border-left: 4px solid #007bff; margin: 10px 0; font-family: monospace; font-size: 12px; }
        .url-list { max-height: 200px; overflow-y: auto; }
        .method-item { margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🇬🇧 UDEA Language Flag Analysis</h1>
        
        <div class="section info">
            <h2>Test Purpose</h2>
            <p>Analyze how the British flag language switcher works on UDEA website and replicate its functionality to get English product names.</p>
            <table>
                <tr><th>Product Code</th><td>{{ $results['product_code'] }}</td></tr>
                <tr><th>Test Time</th><td>{{ $results['timestamp'] }}</td></tr>
                <tr><th>Approach</th><td>Find and replicate the client-side language switching mechanism</td></tr>
            </table>
        </div>

        @if(isset($results['error']))
            <div class="section error">
                <h2>❌ Global Error</h2>
                <p>{{ $results['error'] }}</p>
            </div>
        @endif

        {{-- Homepage Analysis --}}
        @if(isset($results['homepage_analysis']))
            <div class="section {{ $results['homepage_analysis']['success'] ? 'success' : 'error' }}">
                <h2>🏠 Step 1: Homepage Flag Analysis</h2>
                @php $test = $results['homepage_analysis']; @endphp
                
                <table>
                    <tr>
                        <th>Status</th>
                        <td class="{{ $test['success'] ? 'status-good' : 'status-bad' }}">
                            {{ $test['success'] ? 'SUCCESS' : 'FAILED' }}
                        </td>
                    </tr>
                </table>

                @if($test['success'])
                    <h3>🚩 Flag Elements Found</h3>
                    @foreach($test['flag_elements'] as $type => $elements)
                        <h4>{{ ucfirst(str_replace('_', ' ', $type)) }}</h4>
                        <div class="code-snippet">
                            @foreach($elements as $element)
                                <div>{{ $element }}</div>
                            @endforeach
                        </div>
                    @endforeach

                    @if(!empty($test['scripts']))
                        <h3>📜 Language-Related JavaScript</h3>
                        @foreach($test['scripts'] as $script)
                            <div class="code-snippet">{{ $script }}</div>
                        @endforeach
                    @endif

                    @if(!empty($test['cookies_before']))
                        <h3>🍪 Initial Cookies</h3>
                        <div class="code-snippet">
                            @foreach($test['cookies_before'] as $cookie)
                                <div>{{ $cookie }}</div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        @endif

        {{-- Flag Analysis --}}
        @if(isset($results['flag_analysis']))
            <div class="section {{ $results['flag_analysis']['flag_found'] ? 'success' : 'warning' }}">
                <h2>🔍 Step 2: Flag Functionality Analysis</h2>
                @php $analysis = $results['flag_analysis']; @endphp
                
                <table>
                    <tr>
                        <th>Flag Functionality Found</th>
                        <td class="{{ $analysis['flag_found'] ? 'status-good' : 'status-warning' }}">
                            {{ $analysis['flag_found'] ? 'YES' : 'NO' }}
                        </td>
                    </tr>
                </table>

                @if(!empty($analysis['ajax_endpoints']))
                    <h3>🌐 AJAX Endpoints Found</h3>
                    <div class="code-snippet">
                        @foreach($analysis['ajax_endpoints'] as $endpoint)
                            <div>{{ $endpoint }}</div>
                        @endforeach
                    </div>
                @endif

                @if(!empty($analysis['cookie_operations']))
                    <h3>🍪 Cookie Operations Found</h3>
                    <div class="code-snippet">
                        @foreach($analysis['cookie_operations'] as $operation)
                            <div>{{ $operation }}</div>
                        @endforeach
                    </div>
                @endif

                @if(!empty($analysis['javascript_handlers']))
                    <h3>⚡ JavaScript Handlers Found</h3>
                    <div class="code-snippet">
                        @foreach($analysis['javascript_handlers'] as $handler)
                            <div>{{ $handler }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Language Switch Test --}}
        @if(isset($results['language_switch_test']))
            <div class="section {{ $results['language_switch_test']['success'] ? 'success' : 'warning' }}">
                <h2>🔄 Step 3: Language Switch Test</h2>
                @php $test = $results['language_switch_test']; @endphp
                
                <table>
                    <tr>
                        <th>Language Switch Success</th>
                        <td class="{{ $test['success'] ? 'status-good' : 'status-warning' }}">
                            {{ $test['success'] ? 'YES' : 'NO' }}
                        </td>
                    </tr>
                    @if($test['success'] && isset($test['successful_method']))
                        <tr>
                            <th>Successful Method</th>
                            <td class="status-good">{{ $test['successful_method'] }}</td>
                        </tr>
                    @endif
                </table>

                <h3>🧪 Methods Tested</h3>
                @foreach($test['methods_tested'] as $index => $method)
                    <div class="method-item {{ $method['success'] ? 'success' : 'error' }}">
                        <h4>Method {{ $index + 1 }}: {{ $method['method'] }}</h4>
                        <table>
                            <tr>
                                <th>Status</th>
                                <td class="{{ $method['success'] ? 'status-good' : 'status-bad' }}">
                                    {{ $method['success'] ? 'SUCCESS' : 'FAILED' }}
                                </td>
                            </tr>
                            @if(isset($method['language_detected']))
                                <tr>
                                    <th>Language Detected</th>
                                    <td class="language-{{ $method['language_detected'] }}">
                                        {{ strtoupper($method['language_detected']) }}
                                    </td>
                                </tr>
                            @endif
                            @if(isset($method['contains_english_indicators']))
                                <tr>
                                    <th>Contains English Indicators</th>
                                    <td class="{{ $method['contains_english_indicators'] ? 'status-good' : 'status-bad' }}">
                                        {{ $method['contains_english_indicators'] ? 'YES' : 'NO' }}
                                    </td>
                                </tr>
                            @endif
                            @if(isset($method['response_code']))
                                <tr>
                                    <th>Response Code</th>
                                    <td>{{ $method['response_code'] }}</td>
                                </tr>
                            @endif
                            @if(isset($method['response_body']))
                                <tr>
                                    <th>Response Body</th>
                                    <td class="code-snippet">{{ $method['response_body'] }}</td>
                                </tr>
                            @endif
                            @if(isset($method['error']))
                                <tr>
                                    <th>Error</th>
                                    <td class="status-bad">{{ $method['error'] }}</td>
                                </tr>
                            @endif
                        </table>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Search After Switch --}}
        @if(isset($results['search_after_switch']))
            <div class="section {{ $results['search_after_switch']['success'] ? 'success' : 'error' }}">
                <h2>🔍 Step 4: Search After Language Switch</h2>
                @php $search = $results['search_after_switch']; @endphp
                
                <table>
                    <tr>
                        <th>Search Success</th>
                        <td class="{{ $search['success'] ? 'status-good' : 'status-bad' }}">
                            {{ $search['success'] ? 'SUCCESS' : 'FAILED' }}
                        </td>
                    </tr>
                    @if($search['success'])
                        <tr>
                            <th>Language Detected</th>
                            <td class="language-{{ $search['language_detected'] }}">
                                {{ strtoupper($search['language_detected']) }}
                            </td>
                        </tr>
                        <tr>
                            <th>Total Product Links</th>
                            <td class="{{ count($search['product_links']) > 0 ? 'status-good' : 'status-warning' }}">
                                {{ count($search['product_links']) }}
                            </td>
                        </tr>
                        <tr>
                            <th>English Links (/products/)</th>
                            <td class="{{ $search['product_links_english'] > 0 ? 'status-good' : 'status-bad' }}">
                                {{ $search['product_links_english'] }}
                            </td>
                        </tr>
                        <tr>
                            <th>Dutch Links (/producten/)</th>
                            <td class="{{ $search['product_links_dutch'] > 0 ? 'status-warning' : 'status-good' }}">
                                {{ $search['product_links_dutch'] }}
                            </td>
                        </tr>
                        
                        @if(count($search['product_links']) > 0)
                            <tr>
                                <th>Product Links Found</th>
                                <td class="url-list">
                                    @foreach($search['product_links'] as $link)
                                        <div class="{{ strpos($link, '/products/') !== false ? 'status-good' : 'status-bad' }}">
                                            {{ $link }}
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endif

                        @if(isset($search['html_sample']))
                            <tr>
                                <th>HTML Sample</th>
                                <td><pre>{{ $search['html_sample'] }}</pre></td>
                            </tr>
                        @endif
                    @endif
                    @if(isset($search['error']))
                        <tr><th>Error</th><td class="status-bad">{{ $search['error'] }}</td></tr>
                    @endif
                </table>
            </div>
        @endif

        <div class="section info">
            <h2>🎯 Results Analysis</h2>
            
            @if((isset($results['search_after_switch']['product_links_english']) && $results['search_after_switch']['product_links_english'] > 0))
                <div class="success" style="padding: 15px; margin: 10px 0;">
                    <h3>🎉 SUCCESS!</h3>
                    <p>We found a way to get English product links! The successful method can be implemented in the main scraping service.</p>
                </div>
            @elseif((isset($results['language_switch_test']['success']) && $results['language_switch_test']['success']))
                <div class="warning" style="padding: 15px; margin: 10px 0;">
                    <h3>⚠️ Partial Success</h3>
                    <p>Language switching works but still returns Dutch product links. UDEA might not have English versions of all products.</p>
                </div>
            @else
                <div class="info" style="padding: 15px; margin: 10px 0;">
                    <h3>🔍 Investigation Needed</h3>
                    <p>No clear language switching mechanism found. UDEA might use a different approach or the flag functionality might be more complex.</p>
                </div>
            @endif

            <h3>Next Steps:</h3>
            <ul>
                <li>If English links were found: Implement the successful method in UdeaScrapingService</li>
                <li>If no English links found: UDEA might not have English product names available</li>
                <li>If flag elements found: Manual inspection of the flag HTML/JavaScript might be needed</li>
            </ul>
        </div>

        <div class="section info">
            <h2>Quick Test Links</h2>
            <ul>
                <li><a href="?product_code=115">Test Product 115 (Broccoli)</a></li>
                <li><a href="?product_code=2192">Test Product 2192 (Ice cream)</a></li>
                <li><a href="?product_code=161">Test Product 161 (Tomatoes)</a></li>
            </ul>
        </div>
    </div>
</body>
</html>