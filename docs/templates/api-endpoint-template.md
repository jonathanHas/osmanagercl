# API Endpoint: [Endpoint Name]

## Overview
Brief description of what this endpoint does and when to use it.

## Endpoint Details

- **URL**: `/api/resource/{id}/action`
- **Method**: `POST`
- **Authentication**: Required (Bearer token)
- **Permissions**: `resource.action` permission required

## Request

### Headers
| Header | Value | Required | Description |
|--------|-------|----------|-------------|
| Authorization | Bearer {token} | Yes | Authentication token |
| Content-Type | application/json | Yes | Request content type |
| Accept | application/json | Yes | Expected response type |

### URL Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Resource identifier |

### Query Parameters
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| include | string | null | Comma-separated list of relationships to include |
| page | integer | 1 | Page number for pagination |
| per_page | integer | 15 | Items per page |

### Request Body
```json
{
    "field1": "value1",
    "field2": 123,
    "nested": {
        "subfield": "value"
    },
    "array_field": ["item1", "item2"]
}
```

### Field Descriptions
- **field1** (string, required): Description of field1
- **field2** (integer, optional): Description of field2
- **nested.subfield** (string, required): Description of nested field
- **array_field** (array, optional): Array of string values

### Validation Rules
- `field1`: required, string, max:255
- `field2`: integer, min:0, max:1000
- `nested.subfield`: required_with:nested, string
- `array_field.*`: string, distinct

## Response

### Success Response (200 OK)
```json
{
    "success": true,
    "data": {
        "id": 123,
        "field1": "value1",
        "field2": 123,
        "created_at": "2024-01-20T10:30:00Z",
        "updated_at": "2024-01-20T10:30:00Z"
    },
    "message": "Resource processed successfully"
}
```

### Error Responses

#### Validation Error (422 Unprocessable Entity)
```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "field1": ["The field1 field is required."],
        "field2": ["The field2 must be an integer."]
    }
}
```

#### Not Found (404 Not Found)
```json
{
    "success": false,
    "message": "Resource not found"
}
```

#### Unauthorized (401 Unauthorized)
```json
{
    "message": "Unauthenticated."
}
```

#### Forbidden (403 Forbidden)
```json
{
    "success": false,
    "message": "You do not have permission to perform this action"
}
```

#### Server Error (500 Internal Server Error)
```json
{
    "success": false,
    "message": "An error occurred while processing your request"
}
```

## Examples

### cURL
```bash
curl -X POST https://example.com/api/resource/123/action \
  -H "Authorization: Bearer your-token-here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "field1": "value1",
    "field2": 123
  }'
```

### JavaScript (Fetch)
```javascript
const response = await fetch('/api/resource/123/action', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    body: JSON.stringify({
        field1: 'value1',
        field2: 123
    })
});

const data = await response.json();
```

### PHP (Guzzle)
```php
$client = new \GuzzleHttp\Client();
$response = $client->post('https://example.com/api/resource/123/action', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ],
    'json' => [
        'field1' => 'value1',
        'field2' => 123,
    ],
]);

$data = json_decode($response->getBody(), true);
```

## Rate Limiting
- Rate limit: 60 requests per minute
- Headers returned:
  - `X-RateLimit-Limit`: Maximum requests per window
  - `X-RateLimit-Remaining`: Requests remaining in current window
  - `X-RateLimit-Reset`: Timestamp when the rate limit resets

## Notes
- This endpoint is idempotent - multiple identical requests produce the same result
- Large requests may be subject to additional processing time
- Results are cached for 5 minutes

## Change Log
| Version | Date | Changes |
|---------|------|---------|
| 1.1 | 2024-01-20 | Added array_field parameter |
| 1.0 | 2024-01-10 | Initial implementation |

## Related Endpoints
- [GET /api/resource](./get-resource.md) - Retrieve resources
- [PUT /api/resource/{id}](./update-resource.md) - Update resource
- [DELETE /api/resource/{id}](./delete-resource.md) - Delete resource