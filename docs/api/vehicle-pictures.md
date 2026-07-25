# Vehicle Model Pictures API Documentation

The **Vehicle Model Pictures API** resolves high-resolution vehicle pictures/photos and core model identifying attributes (Make, Model, Year) using a Vehicle Identification Number (VIN).

---

## Base URL

```http
https://app.ankshipping.com/api/vehicles
```

---

## Endpoints

### 1. Resolve Pictures by VIN Path Parameter

```http
GET https://app.ankshipping.com/api/vehicles/{vin}/pictures
```

#### Path Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `vin` | `string` | **Yes** | 17-character Vehicle Identification Number (case-insensitive). |

---

### 2. Resolve Pictures by Query Parameter

```http
GET https://app.ankshipping.com/api/vehicles/pictures?vin={vin}
```

#### Query Parameters
| Parameter | Type | Required | Description |
| :--- | :--- | :--- | :--- |
| `vin` | `string` | **Yes** | Vehicle Identification Number passed as a query string parameter. |

---

## Request Headers

| Header | Value | Required |
| :--- | :--- | :--- |
| `Accept` | `application/json` | **Yes** |

---

## Response Schema & Fields

### Success Response (`200 OK`)

Returns resolved vehicle model identification and an array of image URLs:

```json
{
  "success": true,
  "vin": "1HGCR2F83HA123456",
  "make": "Honda",
  "model": "Accord",
  "year": "2017",
  "pictures": [
    "https://cs.copart.com/v1/AUTH_svc/images/photo1.jpg",
    "https://cs.copart.com/v1/AUTH_svc/images/photo2.jpg"
  ]
}
```

#### Field Descriptions
| Field | Type | Description |
| :--- | :--- | :--- |
| `success` | `boolean` | Indicates successful resolution (`true`). |
| `vin` | `string` | Normalized 17-character VIN. |
| `make` | `string\|null` | Vehicle manufacturer (e.g. `Honda`, `Toyota`). |
| `model` | `string\|null` | Vehicle model (e.g. `Accord`, `Camry`). |
| `year` | `string\|null` | Vehicle manufacturing year (e.g. `2017`). |
| `pictures` | `array<string>` | List of image URLs associated with the vehicle model. |

---

## Error Responses

### 1. Invalid or Missing VIN (`422 Unprocessable Entity`)

Returned when the `vin` parameter is omitted or contains invalid format characters.

```json
{
  "success": false,
  "message": "A valid VIN parameter is required.",
  "pictures": []
}
```

### 2. Vehicle / Pictures Not Found (`404 Not Found`)

Returned when no vehicle or photo records could be located for the VIN.

```json
{
  "success": false,
  "message": "No vehicle or pictures found for the specified VIN.",
  "pictures": []
}
```

---

## Code Examples

### cURL

```bash
curl -X GET "https://app.ankshipping.com/api/vehicles/1HGCR2F83HA123456/pictures" \
  -H "Accept: application/json"
```

### JavaScript (Fetch)

```javascript
async function getVehiclePictures(vin) {
  const response = await fetch(`https://app.ankshipping.com/api/vehicles/${encodeURIComponent(vin)}/pictures`, {
    headers: {
      'Accept': 'application/json'
    }
  });

  const data = await response.json();
  if (data.success) {
    console.log(`Found ${data.pictures.length} photos for ${data.year} ${data.make} ${data.model}`);
    console.log('Pictures:', data.pictures);
  } else {
    console.error(data.message);
  }
}
```

### PHP (Laravel HTTP Client)

```php
use Illuminate\Support\Facades\Http;

$response = Http::acceptJson()->get("https://app.ankshipping.com/api/vehicles/{$vin}/pictures");

if ($response->successful()) {
    $vehicleData = $response->json();
    $pictures = $vehicleData['pictures'];
}
```

### Python (Requests)

```python
import requests

vin = "1HGCR2F83HA123456"
url = f"https://app.ankshipping.com/api/vehicles/{vin}/pictures"
headers = {"Accept": "application/json"}

response = requests.get(url, headers=headers)
data = response.json()

if response.status_code == 200 and data.get("success"):
    print(f"Vehicle: {data['year']} {data['make']} {data['model']}")
    print(f"Pictures: {data['pictures']}")
```
