<?php

namespace AmeliaBooking\Infrastructure\API\Export;

use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Repository\Booking\Appointment\AppointmentRepository;
use Slim\Http\Request;
use Slim\Http\Response;

class ICSExport
{
    private $container;
    private $appointmentRepository;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->appointmentRepository = $this->container->get('domain.booking.appointment.repository');
    }

    public function export(Request $request, Response $response)
    {
        try {
            // Récupération des rendez-vous avec les informations nécessaires
            $appointments = $this->appointmentRepository->getFiltered([
                'dates' => [],
                'services' => [],
                'providers' => [],
                'status' => 'approved'
            ]);

            // Log pour déboguer
            $debugInfo = "Debug Information:\n";
            $debugInfo .= "Appointments data type: " . get_class($appointments) . "\n";
            $debugInfo .= "Number of appointments: " . $appointments->length() . "\n";
            
            $icsContent = "BEGIN:VCALENDAR\r\n";
            $icsContent .= "VERSION:2.0\r\n";
            $icsContent .= "PRODID:-//Amelia Booking//EN\r\n";
            $icsContent .= "CALSCALE:GREGORIAN\r\n";
            $icsContent .= "METHOD:PUBLISH\r\n";
            $icsContent .= "X-WR-CALNAME:Amelia Calendar\r\n";
            $icsContent .= "X-WR-TIMEZONE:Europe/Zurich\r\n";
            
            if ($appointments->length() > 0) {
                foreach ($appointments->getItems() as $appointment) {
                    $appointmentData = $appointment->toArray();
                    $debugInfo .= "\nProcessing appointment: " . print_r($appointmentData, true) . "\n";
                    
                    try {
                        $bookingStart = $appointment->getBookingStart()->getValue()->format('Y-m-d H:i:s');
                        $bookingEnd = $appointment->getBookingEnd()->getValue()->format('Y-m-d H:i:s');
                        $serviceName = $appointment->getService()->getName()->getValue();
                        
                        // Récupération des informations du client depuis les données de l'appointment
                        $customerName = '';
                        $customerPhone = '';
                        $customerEmail = '';
                        if (isset($appointmentData['bookings'][0]['info'])) {
                            $info = json_decode($appointmentData['bookings'][0]['info'], true);
                            if (isset($info['firstName']) && isset($info['lastName'])) {
                                $customerName = $info['firstName'] . ' ' . $info['lastName'];
                            }
                            if (isset($info['phone'])) {
                                $customerPhone = $info['phone'];
                            }
                            if (isset($info['email'])) {
                                $customerEmail = $info['email'];
                            }
                        }
                        
                        $icsContent .= "BEGIN:VEVENT\r\n";
                        $icsContent .= "DTSTART:" . date('Ymd\THis', strtotime($bookingStart)) . "\r\n";
                        $icsContent .= "DTEND:" . date('Ymd\THis', strtotime($bookingEnd)) . "\r\n";
                        $icsContent .= "SUMMARY:" . $serviceName . " - " . $customerName . "\r\n";
                        $icsContent .= "DESCRIPTION:Client: " . $customerName . "\\n";
                        if ($customerPhone) {
                            $icsContent .= "Téléphone: " . $customerPhone . "\\n";
                        }
                        if ($customerEmail) {
                            $icsContent .= "Email: " . $customerEmail . "\\n";
                        }
                        $icsContent .= "\r\n";
                        $icsContent .= "UID:" . $appointmentData['id'] . "@maudephoto.ch\r\n";
                        $icsContent .= "SEQUENCE:0\r\n";
                        $icsContent .= "DTSTAMP:" . date('Ymd\THis') . "\r\n";
                        $icsContent .= "END:VEVENT\r\n";
                    } catch (\Exception $e) {
                        $debugInfo .= "Error processing appointment: " . $e->getMessage() . "\n";
                        continue;
                    }
                }
            } else {
                $debugInfo .= "No appointments found\n";
            }
            
            $icsContent .= "END:VCALENDAR";
            
            // Ajouter les informations de debug au début du fichier ICS
            $icsContent = "BEGIN:VCALENDAR\r\n" . 
                         "X-WR-CALNAME:Amelia Calendar\r\n" .
                         "X-WR-CALDESC:" . $debugInfo . "\r\n" .
                         substr($icsContent, strlen("BEGIN:VCALENDAR\r\n"));
            
            // En-têtes pour le cache et l'accès public
            $response = $response->withHeader('Content-Type', 'text/calendar; charset=utf-8');
            $response = $response->withHeader('Content-Disposition', 'inline; filename="amelia-calendar.ics"');
            $response = $response->withHeader('Cache-Control', 'no-cache, must-revalidate');
            $response = $response->withHeader('Pragma', 'no-cache');
            $response = $response->withHeader('Expires', '0');
            $response = $response->withHeader('Access-Control-Allow-Origin', '*');
            
            return $response->write($icsContent);
        } catch (\Exception $e) {
            $errorMessage = "ICS Export Error: " . $e->getMessage();
            error_log($errorMessage);
            return $response->withStatus(500)->write($errorMessage);
        }
    }
} 