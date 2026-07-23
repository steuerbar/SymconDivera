<?php

declare(strict_types=1);

class DIVERAEinsatzkachel extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyString('AccessKey', '');
        $this->RegisterPropertyInteger('UpdateInterval', 60);
        $this->RegisterPropertyBoolean('EnableMap', true);
        $this->RegisterAttributeString('MapData', '');
        $this->RegisterAttributeString('MapSignature', '');
        $this->RegisterVariableBoolean('DataValid', 'Daten gültig', '~Switch', 10);
        $this->RegisterVariableBoolean('ActiveAlarm', 'Aktiver Einsatz', '~Switch', 20);
        $this->RegisterVariableInteger('AlarmID', 'Einsatz-ID', '', 30);
        $this->RegisterVariableString('AlarmNumber', 'Einsatznummer', '', 40);
        $this->RegisterVariableString('AlarmTitle', 'Stichwort', '', 50);
        $this->RegisterVariableString('AlarmText', 'Meldung', '', 60);
        $this->RegisterVariableString('AlarmAddress', 'Adresse', '', 70);
        $this->RegisterVariableBoolean('AlarmPriority', 'Priorität', '~Switch', 80);
        $this->RegisterVariableInteger('AlarmTime', 'Alarmierungszeit', '~UnixTimestamp', 90);
        $this->RegisterVariableFloat('AlarmLatitude', 'Breitengrad', '', 100);
        $this->RegisterVariableFloat('AlarmLongitude', 'Längengrad', '', 110);
        $this->RegisterVariableInteger('LastUpdate', 'Letzter Abruf', '~UnixTimestamp', 120);
        $this->RegisterVariableString('LastError', 'Fehlermeldung', '', 130);
        $this->RegisterVariableString('AlarmJSON', 'Einsatz JSON', '', 140);
        $this->RegisterTimer('DataUpdate', 0, 'DVR_UpdateData($_IPS["TARGET"]);');
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $configured = trim($this->ReadPropertyString('AccessKey')) !== '';
        $this->SetTimerInterval('DataUpdate', $configured ? max(15, $this->ReadPropertyInteger('UpdateInterval')) * 1000 : 0);
        $this->SetStatus($configured ? 102 : 104);
        if ($configured) {
            $this->UpdateData();
        } else {
            $this->SetValue('DataValid', false);
            $this->SetValue('LastError', 'Kein DIVERA-Accesskey konfiguriert');
            $this->PushTile();
        }
    }

    public function GetVisualizationTile()
    {
        return str_replace('__INITIAL_STATE__', $this->StateJSON(), file_get_contents(__DIR__ . '/module.html'));
    }

    public function UpdateData()
    {
        $key = trim($this->ReadPropertyString('AccessKey'));
        if ($key === '') {
            $this->SetStatus(104);
            return;
        }
        try {
            $raw = $this->HttpGet('https://app.divera247.com/api/v2/pull/all?accesskey=' . rawurlencode($key), 'application/json');
            $response = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($response) || ($response['success'] ?? false) !== true) {
                throw new RuntimeException((string) ($response['error'] ?? 'DIVERA meldet keinen Erfolg'));
            }
            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            $items = $data['alarm']['items'] ?? [];
            $alarms = is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
            usort($alarms, static function (array $a, array $b): int {
                return ((int) ($b['date'] ?? $b['ts_create'] ?? 0)) <=> ((int) ($a['date'] ?? $a['ts_create'] ?? 0));
            });
            $alarm = null;
            foreach ($alarms as $candidate) {
                if (empty($candidate['deleted']) && empty($candidate['closed']) && empty($candidate['hidden'])) {
                    $alarm = $candidate;
                    break;
                }
            }
            $this->SetValue('ActiveAlarm', $alarm !== null);
            $this->SetValue('AlarmID', $alarm === null ? 0 : (int) ($alarm['id'] ?? 0));
            $this->SetValue('AlarmNumber', $alarm === null ? '' : (string) ($alarm['foreign_id'] ?? $alarm['number'] ?? ''));
            $this->SetValue('AlarmTitle', $alarm === null ? '' : (string) ($alarm['title'] ?? ''));
            $this->SetValue('AlarmText', $alarm === null ? '' : (string) ($alarm['text'] ?? ''));
            $this->SetValue('AlarmAddress', $alarm === null ? '' : (string) ($alarm['address'] ?? ''));
            $this->SetValue('AlarmPriority', $alarm !== null && (bool) ($alarm['priority'] ?? false));
            $this->SetValue('AlarmTime', $alarm === null ? 0 : (int) ($alarm['date'] ?? $alarm['ts_create'] ?? 0));
            $this->SetValue('AlarmJSON', $alarm === null ? '{}' : (string) json_encode($alarm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if ($alarm === null) {
                $this->SetValue('AlarmLatitude', 0.0);
                $this->SetValue('AlarmLongitude', 0.0);
                $this->WriteAttributeString('MapData', '');
                $this->WriteAttributeString('MapSignature', '');
            } else {
                $this->UpdateLocation($alarm);
            }
            $this->SetValue('DataValid', true);
            $this->SetValue('LastUpdate', time());
            $this->SetValue('LastError', '');
            $this->SetStatus(102);
        } catch (Throwable $e) {
            $this->SetValue('DataValid', false);
            $this->SetValue('LastUpdate', time());
            $this->SetValue('LastError', $e->getMessage());
            $this->SetStatus(201);
            $this->SendDebug('DIVERA Update', $e->getMessage(), 0);
        }
        $this->PushTile();
    }

    private function UpdateLocation(array $alarm): void
    {
        $lat = (float) ($alarm['lat'] ?? 0);
        $lng = (float) ($alarm['lng'] ?? 0);
        $address = (string) ($alarm['address'] ?? '');
        if ((abs($lat) < 0.000001 || abs($lng) < 0.000001) && trim($address) !== '') {
            $rows = json_decode($this->HttpGet('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' . rawurlencode($address), 'application/json'), true);
            if (is_array($rows) && isset($rows[0]['lat'], $rows[0]['lon'])) {
                $lat = (float) $rows[0]['lat'];
                $lng = (float) $rows[0]['lon'];
            }
        }
        $this->SetValue('AlarmLatitude', $lat);
        $this->SetValue('AlarmLongitude', $lng);
        $signature = (string) ($alarm['id'] ?? 0) . '|' . $address . '|' . $lat . '|' . $lng;
        if (!$this->ReadPropertyBoolean('EnableMap') || abs($lat) < 0.000001 || abs($lng) < 0.000001) {
            $this->WriteAttributeString('MapData', '');
        } elseif ($this->ReadAttributeString('MapSignature') !== $signature) {
            $this->WriteAttributeString('MapData', base64_encode($this->BuildMap($lat, $lng)));
        }
        $this->WriteAttributeString('MapSignature', $signature);
    }

    private function BuildMap(float $lat, float $lng): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('Die PHP-GD-Erweiterung wird für die Karte benötigt');
        }
        $width = 640; $height = 360; $zoom = 15; $n = 2 ** $zoom;
        $lat = max(-85.0511, min(85.0511, $lat)); $lng = max(-180.0, min(180.0, $lng));
        $worldX = (($lng + 180.0) / 360.0) * $n * 256.0; $rad = deg2rad($lat);
        $worldY = (1.0 - log(tan($rad) + 1.0 / cos($rad)) / M_PI) / 2.0 * $n * 256.0;
        $left = $worldX - $width / 2; $top = $worldY - $height / 2;
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 230, 238, 240));
        for ($y = (int) floor($top / 256); $y <= (int) floor(($top + $height - 1) / 256); $y++) {
            if ($y < 0 || $y >= $n) continue;
            for ($x = (int) floor($left / 256); $x <= (int) floor(($left + $width - 1) / 256); $x++) {
                $wrappedX = (($x % $n) + $n) % $n;
                $tile = @imagecreatefromstring($this->HttpGet('https://tile.openstreetmap.org/' . $zoom . '/' . $wrappedX . '/' . $y . '.png', 'image/png'));
                if ($tile !== false) {
                    imagecopy($canvas, $tile, (int) round($x * 256 - $left), (int) round($y * 256 - $top), 0, 0, 256, 256);
                    imagedestroy($tile);
                }
            }
        }
        $cx = (int) ($width / 2); $cy = (int) ($height / 2); $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledellipse($canvas, $cx, $cy, 28, 28, $white);
        imagefilledellipse($canvas, $cx, $cy, 20, 20, imagecolorallocate($canvas, 220, 45, 72));
        imagefilledellipse($canvas, $cx, $cy, 7, 7, $white);
        imagestring($canvas, 2, $width - 176, $height - 18, '(c) OpenStreetMap contributors', $white);
        ob_start(); imagepng($canvas, null, 7); $png = (string) ob_get_clean(); imagedestroy($canvas);
        return $png;
    }

    private function HttpGet(string $url, string $accept): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 25, CURLOPT_USERAGENT => 'IP-Symcon-DIVERA/2.0', CURLOPT_HTTPHEADER => ['Accept: ' . $accept, 'Accept-Language: de']]);
        $data = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
        if ($data === false || $status < 200 || $status >= 300) throw new RuntimeException('HTTP ' . $status . ($error !== '' ? ': ' . $error : ''));
        return (string) $data;
    }

    private function PushTile(): void
    {
        $this->UpdateVisualizationValue($this->StateJSON());
    }

    private function StateJSON(): string
    {
        $map = $this->ReadAttributeString('MapData');
        return (string) json_encode([
            'valid' => (bool) $this->GetValue('DataValid'), 'active' => (bool) $this->GetValue('ActiveAlarm'),
            'number' => (string) $this->GetValue('AlarmNumber'), 'title' => (string) $this->GetValue('AlarmTitle'),
            'text' => (string) $this->GetValue('AlarmText'), 'address' => (string) $this->GetValue('AlarmAddress'),
            'priority' => (bool) $this->GetValue('AlarmPriority'), 'alarmTime' => (int) $this->GetValue('AlarmTime'),
            'latitude' => (float) $this->GetValue('AlarmLatitude'), 'longitude' => (float) $this->GetValue('AlarmLongitude'),
            'updated' => $this->GetValue('LastUpdate') > 0 ? date('d.m.Y H:i:s', (int) $this->GetValue('LastUpdate')) : '',
            'error' => (string) $this->GetValue('LastError'), 'map' => $map !== '' ? 'data:image/png;base64,' . $map : ''
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
