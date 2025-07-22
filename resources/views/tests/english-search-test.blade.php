<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDEA English Search Test - {{ $results['product_code'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .section { background: white; margin: 20px 0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        h1, h2, h3 { margin-top: 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        .status-good { color: #28a745; font-weight: bold; }
        .status-bad { color: #dc3545; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px 12px; text-align: left; border: 1px solid #ddd; word-break: break-all; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .language-english { background-color: #d4edda; color: #155724; }
        .language-dutch { background-color: #f8d7da; color: #721c24; }
        .language-mixed { background-color: #fff3cd; color: #856404; }
        .language-unknown { background-color: #e2e3e5; color: #6c757d; }
        .test-item { margin: 15px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .url-list { max-height: 200px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 UDEA English Search Test</h1>
        
        <div class="section info">
            <h2>Test Configuration</h2>
            <table>
                <tr><th>Product Code</th><td>{{ $results['product_code'] }}</td></tr>
                <tr><th>Test Time</th><td>{{ $results['timestamp'] }}</td></tr>
                <tr><th>Objective</th><td>Find a way to get English search results (which return English product URLs)</td></tr>
            </table>
        </div>

        @if(isset($results['error']))
            <div class="section error">
                <h2>❌ Global Error</h2>
                <p>{{ $results['error'] }}</p>
            </div>
        @endif

        {{-- Test 1: Homepage Language Analysis --}}
        @if(isset($results['tests']['1_homepage_english']))
            <div class="section">
                <h2>🏠 Test 1: Homepage Language Analysis</h2>
                @php $test = $results['tests']['1_homepage_english']; @endphp
                
                <div class="test-item {{ $test['success'] ? 'success' : 'error' }}">
                    <table>
                        <tr>
                            <th>Status</th>
                            <td class="{{ $test['success'] ? 'status-good' : 'status-bad' }}">
                                {{ $test['success'] ? 'SUCCESS' : 'FAILED' }}
                            </td>
                        </tr>
                        <tr>
                            <th>Language Detected</th>
                            <td class="language-{{ $test['language_detected'] ?? 'unknown' }}">
                                {{ strtoupper($test['language_detected'] ?? 'unknown') }}
                            </td>
                        </tr>
                        @if(isset($test['language_switchers']) && count($test['language_switchers']) > 0)
                            <tr>
                                <th>Language Switchers Found</th>
                                <td>
                                    @foreach($test['language_switchers'] as $url => $html)
                                        <div><strong>URL:</strong> {{ $url }}</div>
                                        <div><strong>HTML:</strong> {{ $html }}</div>
                                        <hr>
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                        @if(isset($test['en_links']) && count($test['en_links']) > 0)
                            <tr>
                                <th>/en/ Links Found</th>
                                <td class="url-list">
                                    @foreach($test['en_links'] as $link)
                                        <div>{{ $link }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                        @if(isset($test['error']))
                            <tr><th>Error</th><td class="status-bad">{{ $test['error'] }}</td></tr>
                        @endif
                    </table>
                </div>
            </div>
        @endif

        {{-- Test 2: English URL Structures --}}
        @if(isset($results['tests']['2_english_urls']))
            <div class="section">
                <h2>🔗 Test 2: English URL Structures</h2>
                @foreach($results['tests']['2_english_urls'] as $index => $test)
                    <div class="test-item {{ $test['success'] ? 'success' : 'error' }}">
                        <h4>URL Test {{ $index + 1 }}</h4>
                        <table>
                            <tr><th>URL Tested</th><td><code>{{ $test['url'] }}</code></td></tr>
                            <tr>
                                <th>Status</th>
                                <td class="{{ $test['success'] ? 'status-good' : 'status-bad' }}">
                                    {{ $test['success'] ? 'SUCCESS' : 'FAILED' }} ({{ $test['status_code'] ?? 'N/A' }})
                                </td>
                            </tr>
                            @if($test['success'])
                                <tr>
                                    <th>Language Detected</th>
                                    <td class="language-{{ $test['language_detected'] }}">
                                        {{ strtoupper($test['language_detected']) }}
                                    </td>
                                </tr>
                                @if(count($test['product_links']) > 0)
                                    <tr>
                                        <th>Product Links Found</th>
                                        <td class="url-list">
                                            @foreach($test['product_links'] as $link)
                                                <div class="{{ strpos($link, '/products/') !== false ? 'status-good' : 'status-bad' }}">
                                                    {{ $link }}
                                                </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @else
                                    <tr><th>Product Links</th><td class="status-warning">None found</td></tr>
                                @endif
                            @endif
                            @if(isset($test['error']))
                                <tr><th>Error</th><td class="status-bad">{{ $test['error'] }}</td></tr>
                            @endif
                        </table>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Test 3: Language Cookies --}}
        @if(isset($results['tests']['3_language_cookies']))
            <div class="section">
                <h2>🍪 Test 3: Language Cookies</h2>
                @foreach($results['tests']['3_language_cookies'] as $index => $test)
                    <div class="test-item {{ $test['success'] ? 'success' : 'error' }}">
                        <h4>Cookie Test {{ $index + 1 }}</h4>
                        <table>
                            <tr>
                                <th>Cookie Tested</th>
                                <td><code>{{ $test['cookie']['name'] }}={{ $test['cookie']['value'] }}</code></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td class="{{ $test['success'] ? 'status-good' : 'status-bad' }}">
                                    {{ $test['success'] ? 'SUCCESS' : 'FAILED' }}
                                </td>
                            </tr>
                            @if($test['success'])
                                <tr>
                                    <th>Language Detected</th>
                                    <td class="language-{{ $test['language_detected'] }}">
                                        {{ strtoupper($test['language_detected']) }}
                                    </td>
                                </tr>
                                @if(count($test['product_links']) > 0)
                                    <tr>
                                        <th>Product Links Found</th>
                                        <td class="url-list">
                                            @foreach($test['product_links'] as $link)
                                                <div class="{{ strpos($link, '/products/') !== false ? 'status-good' : 'status-bad' }}">
                                                    {{ $link }}
                                                </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @else
                                    <tr><th>Product Links</th><td class="status-warning">None found</td></tr>
                                @endif
                            @endif
                            @if(isset($test['error']))
                                <tr><th>Error</th><td class="status-bad">{{ $test['error'] }}</td></tr>
                            @endif
                        </table>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Test 4: Language Parameters --}}
        @if(isset($results['tests']['4_language_params']))
            <div class="section">
                <h2>⚙️ Test 4: Session Language Setup</h2>
                @foreach($results['tests']['4_language_params'] as $index => $test)
                    <div class="test-item {{ $test['success'] ? 'success' : 'error' }}">
                        <h4>Session Test {{ $index + 1 }}</h4>
                        <table>
                            <tr><th>Setup URL</th><td><code>{{ $test['setup_url'] }}</code></td></tr>
                            <tr>
                                <th>Status</th>
                                <td class="{{ $test['success'] ? 'status-good' : 'status-bad' }}">
                                    {{ $test['success'] ? 'SUCCESS' : 'FAILED' }}
                                </td>
                            </tr>
                            @if($test['success'])
                                <tr>
                                    <th>Search Language</th>
                                    <td class="language-{{ $test['search_language'] }}">
                                        {{ strtoupper($test['search_language']) }}
                                    </td>
                                </tr>
                                @if(count($test['product_links']) > 0)
                                    <tr>
                                        <th>Product Links Found</th>
                                        <td class="url-list">
                                            @foreach($test['product_links'] as $link)
                                                <div class="{{ strpos($link, '/products/') !== false ? 'status-good' : 'status-bad' }}">
                                                    {{ $link }}
                                                </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @else
                                    <tr><th>Product Links</th><td class="status-warning">None found</td></tr>
                                @endif
                            @endif
                            @if(isset($test['error']))
                                <tr><th>Error</th><td class="status-bad">{{ $test['error'] }}</td></tr>
                            @endif
                        </table>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="section info">
            <h2>🎯 Analysis</h2>
            <h3>What We're Looking For:</h3>
            <ul>
                <li><span class="status-good">GREEN product links</span> - URLs with <code>/products/product/</code> (English)</li>
                <li><span class="status-bad">RED product links</span> - URLs with <code>/producten/product/</code> (Dutch)</li>
                <li><span class="language-english">ENGLISH search language</span> - Search page returns English product URLs</li>
            </ul>
            
            <h3>Success Criteria:</h3>
            <p>We need to find a method that returns <strong>English product URLs</strong> from the search results. These URLs will then contain English product names instead of Dutch names.</p>
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