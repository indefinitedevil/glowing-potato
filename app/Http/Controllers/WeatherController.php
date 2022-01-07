<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
    const WEATHER_API_CURRENT = 'https://api.weatherapi.com/v1/current.json?key=%s&q=%s';
    const WEATHER_API_FORECAST = 'https://api.weatherapi.com/v1/forecast.json?key=%s&q=%s&days=%d';

    private $request;

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['help']]);
    }

    public function help()
    {
        return response()->json([
            'help' => [
                'weather' => 'You can use the "current", "today", "three-day" and "seven-day" endpoints to get up-to-date weather information.',
                'units' => 'Default units are Celsius for temperature and miles for distance. These can be changed with the "set-units" endpoint. Appropriate values are "c" or "f" for "temp", and "mph" or "kph" for "speed".',
                'location' => 'Default location is "Manchester, UK". This can be changed with the "set-location" endpoint using the "location" parameter.',
                'raw-data' => 'Raw data can be requested by sending a value for raw with the GET request.',
                'dynamic-settings' => 'Units and location can be changed for a particular call by setting values on the GET request. These will not be saved for future use.',
            ],
        ]);
    }

    protected function getLocation(): string
    {
        return $this->request->location ?: $this->request->session()->get('location') ?: 'Manchester, UK';
    }

    protected function getTempUnit(): string
    {
        return $this->request->temp ?: $this->request->session()->get('temp') ?: 'c';
    }

    protected function getSpeedUnit(): string
    {
        return $this->request->speed ?: $this->request->session()->get('speed') ?: 'mph';
    }

    public function setLocation(Request $request): JsonResponse
    {
        if (!empty($request->location)) {
            $request->session()->put('location', $request->location);
            $request->session()->save();
            return $this->sendSuccess('Location set: ' . $request->location);
        }
        return $this->sendError('Location not provided');
    }

    public function setUnits(Request $request): JsonResponse
    {
        $provided = false;
        if (!empty($request->temp)) {
            $temp = strtolower($request->temp);
            if (in_array($temp, ['c', 'f'])) {
                $request->session()->put('temp', $temp);
                $request->session()->save();
                $provided = true;
            }
        }
        if (!empty($request->speed)) {
            $speed = strtolower($request->speed);
            if (in_array($speed, ['mph', 'kph'])) {
                $request->session()->put('speed', $speed);
                $request->session()->save();
                $provided = true;
            }
        }
        if ($provided) {
            return $this->sendSuccess('Units set');
        }
        return $this->sendError('Units not provided. Pass parameters "temp" and "speed" to set units.');
    }

    public function current(Request $request): JsonResponse
    {
        $this->request = $request;
        $url = sprintf(self::WEATHER_API_CURRENT, env('WEATHER_API_KEY'), $this->getLocation());
        return $this->fetchWeather($url);
    }

    public function today(Request $request): JsonResponse
    {
        $this->request = $request;
        $url = sprintf(self::WEATHER_API_FORECAST, env('WEATHER_API_KEY'), $this->getLocation(), 1);
        return $this->fetchWeather($url);
    }

    public function forecast3(Request $request): JsonResponse
    {
        $this->request = $request;
        $url = sprintf(self::WEATHER_API_FORECAST, env('WEATHER_API_KEY'), $this->getLocation(), 3);
        return $this->fetchWeather($url);
    }

    public function forecast7(Request $request): JsonResponse
    {
        $this->request = $request;
        $url = sprintf(self::WEATHER_API_FORECAST, env('WEATHER_API_KEY'), $this->getLocation(), 7);
        return $this->fetchWeather($url);
    }

    protected function fetchWeather($url): JsonResponse
    {
        if (empty(env('WEATHER_API_KEY'))) {
            return $this->sendError('API key missing.');
        }
        $response = Http::get($url);
        if (empty($response['location'])) {
            return $this->sendError('Invalid location provided.');
        }
        if (empty($response['current'])) {
            return $this->sendError('Could not fetch weather details.');
        }
        $response = $this->formatResponse($response->json());
        return $this->sendResponse($response);
    }

    protected function formatResponse($response): array
    {
        $formatted = [
            'success' => true,
            'data' => [
                'location' => $this->formatLocation($response['location']),
                'current' => $this->formatWeather($response['current']),
            ],
        ];
        if (!empty($response['forecast'])) {
            $formatted['data']['forecast'] = $this->formatForecast($response['forecast']);
        }
        if (!empty($this->request->raw)) {
            $formatted['rawData'] = $response;
        }
        return $formatted;
    }

    protected function formatLocation($location): array
    {
        $keys = ['name', 'region', 'country'];
        return array_intersect_key($location, array_flip($keys));
    }

    protected function formatWeather($weather): array
    {
        $keys = [
            'last_updated',
            'time',
            'condition',
            'temp_' . $this->getTempUnit(),
            'maxtemp_' . $this->getTempUnit(),
            'mintemp_' . $this->getTempUnit(),
            'avgtemp_' . $this->getTempUnit(),
            'feelslike_' . $this->getTempUnit(),
            'wind_' . $this->getSpeedUnit(),
            'maxwind_' . $this->getSpeedUnit(),
            'wind_dir',
            'wind_degree',
            'gust_' . $this->getSpeedUnit(),
            'humidity',
            'avghumidity',
            'cloud',
            'vis_' . substr($this->getSpeedUnit(), 0, 1) == 'k' ? 'km' : 'miles',
            'avgvis_' . substr($this->getSpeedUnit(), 0, 1) == 'k' ? 'km' : 'miles',
            'daily_will_it_snow',
            'will_it_snow',
            'chance_of_snow',
            'daily_chance_of_snow',
            'will_it_rain',
            'daily_will_it_rain',
            'chance_of_rain',
            'daily_chance_of_rain',
        ];
        $data = array_intersect_key($weather, array_flip($keys));
        $data['condition'] = $data['condition']['text'];
        return $data;
    }

    protected function formatForecast($forecast): array
    {
        $formatted = [];
        $today = date('Y-m-d');
        $hour = date('Y-m-d H:00');
        foreach ($forecast['forecastday'] as $key => $forecastDay) {
            $formatted[$key] = [
                'date' => $forecastDay['date'],
                'day' => $this->formatWeather($forecastDay['day']),
            ];
            foreach ($forecastDay['hour'] as $forecastHour) {
                if ($today == $forecastDay['date'] && $hour > $forecastHour['time']) {
                    continue;
                }
                $formatted[$key]['hour'][] = $this->formatWeather($forecastHour);
            }
        }
        return $formatted;
    }

    protected function sendSuccess($message): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message]);
    }

    protected function sendResponse($response): JsonResponse
    {
        return response()->json($response);
    }

    protected function sendError($message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 400);
    }
}
