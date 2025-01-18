<?php
$apiKey = 'ae618f54aa11d9340b312a3c491859fe'; // Your OpenWeatherMap API Key
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'weather_db';

// Create a MySQL connection
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

$city = $_GET['city'] ?? '';
$response = [];

if ($city) {
    $sql = "SELECT * FROM weather_cache WHERE city_name = ? AND TIMESTAMPDIFF(MINUTE, timestamp, NOW()) < 30";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $city);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $response = [
            'city_name' => $row['city_name'],
            'country_code' => $row['country_code'],
            'temperature' => $row['temperature'],
            'description' => $row['description']
        ];
    } else {
        $url = "https://api.openweathermap.org/data/2.5/weather?q={$city}&appid={$apiKey}";
        $data = file_get_contents($url);

        if ($data === FALSE) {
            echo json_encode(['error' => 'Failed to retrieve weather data from the API.']);
            exit;
        }

        $weatherData = json_decode($data, true);

        if (isset($weatherData['cod']) && $weatherData['cod'] == 200) {
            $temperature = round($weatherData['main']['temp'] - 273.15, 2);
            $description = $weatherData['weather'][0]['description'];
            $cityName = $weatherData['name'];
            $country = $weatherData['sys']['country'];

            $sql = "INSERT INTO weather_cache (city_name, country_code, temperature, description) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssss", $cityName, $country, $temperature, $description);
            $stmt->execute();

            $response = [
                'city_name' => $cityName,
                'country_code' => $country,
                'temperature' => $temperature,
                'description' => $description
            ];
        } else {
            $response = ['error' => 'City not found or invalid API response.'];
        }
    }
} else {
    $response = ['error' => 'City name is required.'];
}

$conn->close();
header('Content-Type: application/json');
echo json_encode($response);
