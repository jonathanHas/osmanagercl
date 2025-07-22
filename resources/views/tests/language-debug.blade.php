<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UDEA Language Debug - {{ $debugInfo['product_code'] }}</title>
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
        th, td { padding: 8px 12px; text-align: left; border: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: bold; }
        .test-row { margin-bottom: 30px; }
        .language-dutch { background-color: #fff3cd; }
        .language-english { background-color: #d4edda; }
        .language-mixed { background-color: #e2e3e5; }
        .language-unknown { background-color: #f8d7da; }
    </style>
</head>
<body>
    <div class="container">
        <h1>UDEA Language Control Debug</h1>
        
        <div class="section info">
            <h2>Test Configuration</h2>
            <table>
                <tr><th>Product Code</th><td>{{ $debugInfo['product_code'] }}</td></tr>
                <tr><th>Test Time</th><td>{{ $debugInfo['timestamp'] }}</td></tr>
                <tr><th>Tests Performed</th><td>{{ count($debugInfo['tests']) }}</td></tr>
            </table>
        </div>

        @if(isset($debugInfo['error']))
            <div class="section error">
                <h2>❌ Global Error</h2>
                <p>{{ $debugInfo['error'] }}</p>
            </div>
        @endif

        <div class="section">
            <h2>Language Control Test Results</h2>
            <p>Testing different approaches to force English language content from UDEA website.</p>
            
            @foreach($debugInfo['tests'] as $testKey => $test)
                <div class="test-row {{ $test['success'] ? 'section success' : 'section error' }}">
                    <h3>{{ ucfirst(str_replace('_', ' ', $testKey)) }}</h3>
                    <p><strong>Method:</strong> {{ $test['description'] }}</p>
                    
                    <table>
                        <tr>
                            <th>Status</th>
                            <td class="{{ $test['success'] ? 'status-good' : 'status-bad' }}">
                                {{ $test['success'] ? 'SUCCESS' : 'FAILED' }}
                            </td>
                        </tr>
                        <tr>
                            <th>Response Code</th>
                            <td>{{ $test['response_code'] ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>URL Used</th>
                            <td><code>{{ $test['url'] }}</code></td>
                        </tr>
                        <tr>
                            <th>Headers Sent</th>
                            <td>
                                @foreach($test['headers'] as $header => $value)
                                    <code>{{ $header }}: {{ $value }}</code><br>
                                @endforeach
                            </td>
                        </tr>
                        @if($test['success'])
                            <tr>
                                <th>Search Page Language</th>
                                <td class="language-{{ $test['language_detected'] }}">
                                    <strong>{{ strtoupper($test['language_detected']) }}</strong>
                                </td>
                            </tr>
                            @if($test['detail_url'])
                                <tr>
                                    <th>Detail URL Found</th>
                                    <td>
                                        <a href="{{ $test['detail_url'] }}" target="_blank">{{ $test['detail_url'] }}</a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Detail URL Language</th>
                                    <td class="language-{{ $test['detail_language'] }}">
                                        <strong>{{ strtoupper($test['detail_language']) }}</strong>
                                    </td>
                                </tr>
                                @if($test['full_description'])
                                    <tr>
                                        <th>Extracted Product Name</th>
                                        <td class="status-good"><strong>{{ $test['full_description'] }}</strong></td>
                                    </tr>
                                    <tr>
                                        <th>Components</th>
                                        <td>
                                            Brand: {{ $test['brand'] ?? 'N/A' }}<br>
                                            Product: {{ $test['product_name'] ?? 'N/A' }}<br>
                                            Size: {{ $test['size'] ?? 'N/A' }}
                                        </td>
                                    </tr>
                                @else
                                    <tr>
                                        <th>Product Name Extraction</th>
                                        <td class="status-bad">FAILED</td>
                                    </tr>
                                @endif
                            @else
                                <tr>
                                    <th>Detail URL</th>
                                    <td class="status-bad">NOT FOUND</td>
                                </tr>
                            @endif
                        @endif
                        @if(isset($test['error']))
                            <tr>
                                <th>Error</th>
                                <td class="status-bad">{{ $test['error'] }}</td>
                            </tr>
                        @endif
                    </table>
                </div>
            @endforeach
        </div>

        <div class="section info">
            <h2>Analysis</h2>
            <h3>Language Detection Legend</h3>
            <ul>
                <li><span class="language-english status-good">ENGLISH</span> - Content is in English (desired)</li>
                <li><span class="language-dutch status-bad">DUTCH</span> - Content is in Dutch (current issue)</li>
                <li><span class="language-mixed status-warning">MIXED</span> - Contains both languages</li>
                <li><span class="language-unknown status-warning">UNKNOWN</span> - Cannot determine language</li>
            </ul>
            
            <h3>Next Steps</h3>
            <p>Based on these results, we can identify which approach works best for forcing English content and implement that in the main scraping service.</p>
        </div>

        <div class="section info">
            <h2>Quick Test Links</h2>
            <p>Test other products:</p>
            <ul>
                <li><a href="?product_code=115">Test Product 115 (Broccoli)</a></li>
                <li><a href="?product_code=2192">Test Product 2192 (Ice cream)</a></li>
                <li><a href="?product_code=161">Test Product 161 (Tomatoes)</a></li>
            </ul>
        </div>
    </div>
</body>
</html>