<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDEA Authentication Test - {{ $results['product_code'] }}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .section { background: white; margin: 20px 0; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .error { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .info { background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        h1, h2, h3 { margin-top: 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto; }
        .status-good { color: #28a745; font-weight: bold; }
        .status-bad { color: #dc3545; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px 12px; text-align: left; border: 1px solid #ddd; word-break: break-word; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .language-english { background-color: #d4edda; color: #155724; }
        .language-dutch { background-color: #f8d7da; color: #721c24; }
        .language-mixed { background-color: #fff3cd; color: #856404; }
        .language-unknown { background-color: #e2e3e5; color: #6c757d; }
        .url-list { max-height: 200px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 UDEA Authentication Comparison Test</h1>
        
        <div class="section info">
            <h2>Test Purpose</h2>
            <p>Compare the working UdeaScrapingService with our manual authentication approach to identify why our English search tests aren't finding product links.</p>
            <table>
                <tr><th>Product Code</th><td>{{ $results['product_code'] }}</td></tr>
                <tr><th>Test Time</th><td>{{ $results['timestamp'] }}</td></tr>
            </table>
        </div>

        @if(isset($results['error']))
            <div class="section error">
                <h2>❌ Global Error</h2>
                <p>{{ $results['error'] }}</p>
            </div>
        @endif

        {{-- Working Service Test --}}
        @if(isset($results['working_service_test']))
            <div class="section {{ $results['working_service_test']['success'] ? 'success' : 'error' }}">
                <h2>✅ Test 1: Working UdeaScrapingService</h2>
                @php $test = $results['working_service_test']; @endphp
                
                <table>
                    <tr>
                        <th>Status</th>
                        <td class="{{ $test['success'] ? 'status-good' : 'status-bad' }}">
                            {{ $test['success'] ? 'SUCCESS' : 'FAILED' }}
                        </td>
                    </tr>
                    @if($test['success'] && isset($test['data']))
                        <tr>
                            <th>Product Name Found</th>
                            <td class="status-good">{{ $test['product_name'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Brand</th>
                            <td>{{ $test['brand'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Size</th>
                            <td>{{ $test['size'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Complete Data</th>
                            <td>
                                <pre>{{ json_encode($test['data'], JSON_PRETTY_PRINT) }}</pre>
                            </td>
                        </tr>
                    @endif
                    @if(isset($test['error']))
                        <tr><th>Error</th><td class="status-bad">{{ $test['error'] }}</td></tr>
                    @endif
                </table>
            </div>
        @endif

        {{-- Manual Auth Test --}}
        @if(isset($results['manual_auth_test']))
            <div class="section {{ $results['manual_auth_test']['success'] ? 'success' : 'error' }}">
                <h2>🔧 Test 2: Manual Authentication Method</h2>
                @php $test = $results['manual_auth_test']; @endphp
                
                <table>
                    <tr>
                        <th>Status</th>
                        <td class="{{ $test['success'] ? 'status-good' : 'status-bad' }}">
                            {{ $test['success'] ? 'SUCCESS' : 'FAILED' }}
                        </td>
                    </tr>
                    <tr>
                        <th>Authentication Status</th>
                        <td class="{{ in_array($test['authentication_status'], [200, 302]) ? 'status-good' : 'status-bad' }}">
                            {{ $test['authentication_status'] }}
                        </td>
                    </tr>
                    @if($test['success'])
                        <tr>
                            <th>Language Detected</th>
                            <td class="language-{{ $test['language_detected'] }}">
                                {{ strtoupper($test['language_detected']) }}
                            </td>
                        </tr>
                        <tr>
                            <th>Contains Products List</th>
                            <td class="{{ $test['contains_products_list'] ? 'status-good' : 'status-bad' }}">
                                {{ $test['contains_products_list'] ? 'YES' : 'NO' }}
                            </td>
                        </tr>
                        <tr>
                            <th>Contains Product Code</th>
                            <td class="{{ $test['contains_product_code'] ? 'status-good' : 'status-bad' }}">
                                {{ $test['contains_product_code'] ? 'YES' : 'NO' }}
                            </td>
                        </tr>
                        <tr>
                            <th>Page Title</th>
                            <td>{{ $test['html_title'] }}</td>
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
                            <tr>
                                <th>Product Links</th>
                                <td class="status-warning">None found</td>
                            </tr>
                        @endif
                        <tr>
                            <th>Search HTML Sample</th>
                            <td>
                                <pre>{{ $test['search_html_sample'] }}</pre>
                            </td>
                        </tr>
                    @endif
                    @if(isset($test['error']))
                        <tr><th>Error</th><td class="status-bad">{{ $test['error'] }}</td></tr>
                    @endif
                </table>
            </div>
        @endif

        {{-- Comparison --}}
        @if(isset($results['comparison']))
            <div class="section info">
                <h2>🔍 Comparison Analysis</h2>
                @php $comp = $results['comparison']; @endphp
                
                <table>
                    <tr>
                        <th>Working Service Succeeds</th>
                        <td class="{{ $comp['working_service_succeeds'] ? 'status-good' : 'status-bad' }}">
                            {{ $comp['working_service_succeeds'] ? 'YES' : 'NO' }}
                        </td>
                    </tr>
                    <tr>
                        <th>Manual Auth Succeeds</th>
                        <td class="{{ $comp['manual_auth_succeeds'] ? 'status-good' : 'status-bad' }}">
                            {{ $comp['manual_auth_succeeds'] ? 'YES' : 'NO' }}
                        </td>
                    </tr>
                    <tr>
                        <th>Working Service Finds Product</th>
                        <td class="{{ $comp['working_service_finds_product'] ? 'status-good' : 'status-bad' }}">
                            {{ $comp['working_service_finds_product'] ? 'YES' : 'NO' }}
                        </td>
                    </tr>
                    <tr>
                        <th>Manual Auth Finds Links</th>
                        <td class="{{ $comp['manual_auth_finds_links'] ? 'status-good' : 'status-bad' }}">
                            {{ $comp['manual_auth_finds_links'] ? 'YES' : 'NO' }}
                        </td>
                    </tr>
                    <tr>
                        <th>Authentication Difference</th>
                        <td class="{{ $comp['authentication_difference'] ? 'status-warning' : 'status-good' }}">
                            {{ $comp['authentication_difference'] ? 'YES' : 'NO' }}
                        </td>
                    </tr>
                    <tr>
                        <th>Possible Issue</th>
                        <td class="status-warning">{{ $comp['possible_issue'] }}</td>
                    </tr>
                </table>
            </div>
        @endif

        <div class="section info">
            <h2>🎯 Next Steps</h2>
            <p>Based on this comparison, we can identify:</p>
            <ul>
                <li><strong>If the working service gets Dutch names</strong> - we know UDEA doesn't have English versions of these product names</li>
                <li><strong>If authentication methods differ</strong> - we need to match the working service's approach</li>
                <li><strong>If search results differ</strong> - we can understand why our language tests aren't finding products</li>
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