<?php

namespace Workdo\Appointment\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateLang;
use App\Models\User;

class EmailTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('type','company')->first();

        $emailTemplate = [
            'Appointment Booked',
            'Appointment Callback',
            'Appointment Status Update',
            'Appointment Callback Status Update',
        ];

        $defaultTemplate = [
            'Appointment Booked' => [
                'subject' => 'Appointment Confirmation - {appointment_name}',
                'variables' => '{
                    "App Name": "app_name",
                    "Company Name": "company_name",
                    "App Url": "app_url",
                    "Appointment Name": "appointment_name",
                    "Appointment User Name": "appointment_user_name",
                    "Appointment User Email": "appointment_user_email",
                    "Appointment Date": "appointment_date",
                    "Appointment Time": "appointment_time",
                    "Appointment Number": "appointment_number"
                  }',
                  'lang' => [
                    'ar' => '<p>مرحبا {appointment_user_name}،</p><p>تم حجز موعدك بنجاح!</p><p><strong>تفاصيل الموعد:</strong></p><ul><li>الموعد: {appointment_name}</li><li>التاريخ: {appointment_date}</li><li>الوقت: {appointment_time}</li><li>رقم الموعد: {appointment_number}</li></ul><p>شكرا لاختيارك {company_name}.</p>',
                    'da' => '<p>Hej {appointment_user_name},</p><p>Din aftale er blevet booket med succes!</p><p><strong>Aftaledetaljer:</strong></p><ul><li>Aftale: {appointment_name}</li><li>Dato: {appointment_date}</li><li>Tid: {appointment_time}</li><li>Aftalenummer: {appointment_number}</li></ul><p>Tak fordi du valgte {company_name}.</p>',
                    'de' => '<p>Hallo {appointment_user_name},</p><p>Ihr Termin wurde erfolgreich gebucht!</p><p><strong>Termindetails:</strong></p><ul><li>Termin: {appointment_name}</li><li>Datum: {appointment_date}</li><li>Zeit: {appointment_time}</li><li>Terminnummer: {appointment_number}</li></ul><p>Vielen Dank, dass Sie sich für {company_name} entschieden haben.</p>',
                    'en' => '<p>Hello {appointment_user_name},</p><p>Your appointment has been successfully booked!</p><p><strong>Appointment Details:</strong></p><ul><li>Appointment: {appointment_name}</li><li>Date: {appointment_date}</li><li>Time: {appointment_time}</li><li>Appointment Number: {appointment_number}</li></ul><p>Thank you for choosing {company_name}.</p>',
                    'es' => '<p>Hola {appointment_user_name},</p><p>¡Tu cita ha sido reservada exitosamente!</p><p><strong>Detalles de la Cita:</strong></p><ul><li>Cita: {appointment_name}</li><li>Fecha: {appointment_date}</li><li>Hora: {appointment_time}</li><li>Número de Cita: {appointment_number}</li></ul><p>Gracias por elegir {company_name}.</p>',
                    'fr' => '<p>Bonjour {appointment_user_name},</p><p>Votre rendez-vous a été réservé avec succès!</p><p><strong>Détails du Rendez-vous:</strong></p><ul><li>Rendez-vous: {appointment_name}</li><li>Date: {appointment_date}</li><li>Heure: {appointment_time}</li><li>Numéro de Rendez-vous: {appointment_number}</li></ul><p>Merci d\'avoir choisi {company_name}.</p>',
                    'it' => '<p>Ciao {appointment_user_name},</p><p>Il tuo appuntamento è stato prenotato con successo!</p><p><strong>Dettagli Appuntamento:</strong></p><ul><li>Appuntamento: {appointment_name}</li><li>Data: {appointment_date}</li><li>Ora: {appointment_time}</li><li>Numero Appuntamento: {appointment_number}</li></ul><p>Grazie per aver scelto {company_name}.</p>',
                    'ja' => '<p>こんにちは {appointment_user_name}、</p><p>あなたの予約が正常に予約されました！</p><p><strong>予約詳細:</strong></p><ul><li>予約: {appointment_name}</li><li>日付: {appointment_date}</li><li>時間: {appointment_time}</li><li>予約番号: {appointment_number}</li></ul><p>{company_name}をお選びいただきありがとうございます。</p>',
                    'nl' => '<p>Hallo {appointment_user_name},</p><p>Uw afspraak is succesvol geboekt!</p><p><strong>Afspraak Details:</strong></p><ul><li>Afspraak: {appointment_name}</li><li>Datum: {appointment_date}</li><li>Tijd: {appointment_time}</li><li>Afspraak Nummer: {appointment_number}</li></ul><p>Bedankt voor het kiezen van {company_name}.</p>',
                    'pl' => '<p>Witaj {appointment_user_name},</p><p>Twoja wizyta została pomyślnie zarezerwowana!</p><p><strong>Szczegóły Wizyty:</strong></p><ul><li>Wizyta: {appointment_name}</li><li>Data: {appointment_date}</li><li>Czas: {appointment_time}</li><li>Numer Wizyty: {appointment_number}</li></ul><p>Dziękujemy za wybranie {company_name}.</p>',
                    'pt' => '<p>Olá {appointment_user_name},</p><p>Seu agendamento foi reservado com sucesso!</p><p><strong>Detalhes do Agendamento:</strong></p><ul><li>Agendamento: {appointment_name}</li><li>Data: {appointment_date}</li><li>Hora: {appointment_time}</li><li>Número do Agendamento: {appointment_number}</li></ul><p>Obrigado por escolher {company_name}.</p>',
                    'pt-BR' => '<p>Olá {appointment_user_name},</p><p>Seu agendamento foi reservado com sucesso!</p><p><strong>Detalhes do Agendamento:</strong></p><ul><li>Agendamento: {appointment_name}</li><li>Data: {appointment_date}</li><li>Hora: {appointment_time}</li><li>Número do Agendamento: {appointment_number}</li></ul><p>Obrigado por escolher {company_name}.</p>',
                    'he' => '<p>שלום {appointment_user_name},</p><p>הפגישה שלך נקבעה בהצלחה!</p><p><strong>פרטי הפגישה:</strong></p><ul><li>פגישה: {appointment_name}</li><li>תאריך: {appointment_date}</li><li>שעה: {appointment_time}</li><li>מספר פגישה: {appointment_number}</li></ul><p>תודה שבחרת ב-{company_name}.</p>',
                    'tr' => '<p>Merhaba {appointment_user_name},</p><p>Randevunuz başarıyla rezerve edildi!</p><p><strong>Randevu Detayları:</strong></p><ul><li>Randevu: {appointment_name}</li><li>Tarih: {appointment_date}</li><li>Saat: {appointment_time}</li><li>Randevu Numarası: {appointment_number}</li></ul><p>{company_name} seçtiğiniz için teşekkürler.</p>',
                    'ru' => '<p>Привет {appointment_user_name},</p><p>Ваша встреча была успешно забронирована!</p><p><strong>Детали встречи:</strong></p><ul><li>Встреча: {appointment_name}</li><li>Дата: {appointment_date}</li><li>Время: {appointment_time}</li><li>Номер встречи: {appointment_number}</li></ul><p>Спасибо за выбор {company_name}.</p>',
                    'zh' => '<p>您好 {appointment_user_name}，</p><p>您的预约已成功预订！</p><p><strong>预约详情：</strong></p><ul><li>预约：{appointment_name}</li><li>日期：{appointment_date}</li><li>时间：{appointment_time}</li><li>预约号码：{appointment_number}</li></ul><p>感谢您选择 {company_name}。</p>',
                ],
            ],
            'Appointment Callback' => [
                'subject' => 'Callback Request Received - {appointment_name}',
                'variables' => '{
                    "App Name": "app_name",
                    "Company Name": "company_name",
                    "App Url": "app_url",
                    "Appointment Name": "appointment_name",
                    "Appointment User Name": "appointment_user_name",
                    "Appointment User Email": "appointment_user_email",
                    "Callback Date": "callback_date",
                    "Callback Time": "callback_time",
                    "Callback Reason": "callback_reason"
                  }',
                  'lang' => [
                    'ar' => '<p>مرحبا {appointment_user_name}،</p><p>لقد تلقينا طلب الاتصال المرتد الخاص بك.</p><p><strong>تفاصيل الاتصال المرتد:</strong></p><ul><li>الموعد: {appointment_name}</li><li>التاريخ المطلوب: {callback_date}</li><li>الوقت المطلوب: {callback_time}</li><li>السبب: {callback_reason}</li></ul><p>سنراجع طلبك ونعاود الاتصال بك قريبا.</p><p>شكرا لاختيارك {company_name}.</p>',
                    'da' => '<p>Hej {appointment_user_name},</p><p>Vi har modtaget din tilbagekald anmodning.</p><p><strong>Tilbagekald Detaljer:</strong></p><ul><li>Aftale: {appointment_name}</li><li>Ønsket Dato: {callback_date}</li><li>Ønsket Tid: {callback_time}</li><li>Årsag: {callback_reason}</li></ul><p>Vi vil gennemgå din anmodning og vende tilbage til dig snart.</p><p>Tak fordi du valgte {company_name}.</p>',
                    'de' => '<p>Hallo {appointment_user_name},</p><p>Wir haben Ihre Rückruf-Anfrage erhalten.</p><p><strong>Rückruf-Details:</strong></p><ul><li>Termin: {appointment_name}</li><li>Gewünschtes Datum: {callback_date}</li><li>Gewünschte Zeit: {callback_time}</li><li>Grund: {callback_reason}</li></ul><p>Wir werden Ihre Anfrage prüfen und uns bald bei Ihnen melden.</p><p>Vielen Dank, dass Sie sich für {company_name} entschieden haben.</p>',
                    'en' => '<p>Hello {appointment_user_name},</p><p>We have received your callback request.</p><p><strong>Callback Details:</strong></p><ul><li>Appointment: {appointment_name}</li><li>Requested Date: {callback_date}</li><li>Requested Time: {callback_time}</li><li>Reason: {callback_reason}</li></ul><p>We will review your request and get back to you soon.</p><p>Thank you for choosing {company_name}.</p>',
                    'es' => '<p>Hola {appointment_user_name},</p><p>Hemos recibido tu solicitud de devolución de llamada.</p><p><strong>Detalles de Devolución de Llamada:</strong></p><ul><li>Cita: {appointment_name}</li><li>Fecha Solicitada: {callback_date}</li><li>Hora Solicitada: {callback_time}</li><li>Razón: {callback_reason}</li></ul><p>Revisaremos tu solicitud y te contactaremos pronto.</p><p>Gracias por elegir {company_name}.</p>',
                    'fr' => '<p>Bonjour {appointment_user_name},</p><p>Nous avons reçu votre demande de rappel.</p><p><strong>Détails du Rappel:</strong></p><ul><li>Rendez-vous: {appointment_name}</li><li>Date Demandée: {callback_date}</li><li>Heure Demandée: {callback_time}</li><li>Raison: {callback_reason}</li></ul><p>Nous examinerons votre demande et vous recontacterons bientôt.</p><p>Merci d\'avoir choisi {company_name}.</p>',
                    'it' => '<p>Ciao {appointment_user_name},</p><p>Abbiamo ricevuto la tua richiesta di richiamata.</p><p><strong>Dettagli Richiamata:</strong></p><ul><li>Appuntamento: {appointment_name}</li><li>Data Richiesta: {callback_date}</li><li>Ora Richiesta: {callback_time}</li><li>Motivo: {callback_reason}</li></ul><p>Esamineremo la tua richiesta e ti ricontatteremo presto.</p><p>Grazie per aver scelto {company_name}.</p>',
                    'ja' => '<p>こんにちは {appointment_user_name}、</p><p>コールバックリクエストを受け取りました。</p><p><strong>コールバック詳細:</strong></p><ul><li>予約: {appointment_name}</li><li>希望日: {callback_date}</li><li>希望時間: {callback_time}</li><li>理由: {callback_reason}</li></ul><p>リクエストを確認し、すぐにご連絡いたします。</p><p>{company_name}をお選びいただきありがとうございます。</p>',
                    'nl' => '<p>Hallo {appointment_user_name},</p><p>We hebben uw terugbelverzoek ontvangen.</p><p><strong>Terugbel Details:</strong></p><ul><li>Afspraak: {appointment_name}</li><li>Gewenste Datum: {callback_date}</li><li>Gewenste Tijd: {callback_time}</li><li>Reden: {callback_reason}</li></ul><p>We zullen uw verzoek bekijken en binnenkort contact met u opnemen.</p><p>Bedankt voor het kiezen van {company_name}.</p>',
                    'pl' => '<p>Witaj {appointment_user_name},</p><p>Otrzymaliśmy Twoją prośbę o oddzwonienie.</p><p><strong>Szczegóły Oddzwonienia:</strong></p><ul><li>Wizyta: {appointment_name}</li><li>Żądana Data: {callback_date}</li><li>Żądany Czas: {callback_time}</li><li>Powód: {callback_reason}</li></ul><p>Przejrzymy Twoją prośbę i wkrótce się z Tobą skontaktujemy.</p><p>Dziękujemy za wybranie {company_name}.</p>',
                    'pt' => '<p>Olá {appointment_user_name},</p><p>Recebemos sua solicitação de retorno de chamada.</p><p><strong>Detalhes do Retorno:</strong></p><ul><li>Agendamento: {appointment_name}</li><li>Data Solicitada: {callback_date}</li><li>Hora Solicitada: {callback_time}</li><li>Motivo: {callback_reason}</li></ul><p>Analisaremos sua solicitação e entraremos em contato em breve.</p><p>Obrigado por escolher {company_name}.</p>',
                    'pt-BR' => '<p>Olá {appointment_user_name},</p><p>Recebemos sua solicitação de retorno de chamada.</p><p><strong>Detalhes do Retorno:</strong></p><ul><li>Agendamento: {appointment_name}</li><li>Data Solicitada: {callback_date}</li><li>Hora Solicitada: {callback_time}</li><li>Motivo: {callback_reason}</li></ul><p>Analisaremos sua solicitação e entraremos em contato em breve.</p><p>Obrigado por escolher {company_name}.</p>',
                    'he' => '<p>שלום {appointment_user_name},</p><p>קיבלנו את בקשת החזרת הקריאה שלך.</p><p><strong>פרטי החזרת קריאה:</strong></p><ul><li>פגישה: {appointment_name}</li><li>תאריך מבוקש: {callback_date}</li><li>שעה מבוקשת: {callback_time}</li><li>סיבה: {callback_reason}</li></ul><p>נבדוק את בקשתך ונחזור אליך בקרוב.</p><p>תודה שבחרת ב-{company_name}.</p>',
                    'tr' => '<p>Merhaba {appointment_user_name},</p><p>Geri arama talebinizi aldık.</p><p><strong>Geri Arama Detayları:</strong></p><ul><li>Randevu: {appointment_name}</li><li>İstenen Tarih: {callback_date}</li><li>İstenen Saat: {callback_time}</li><li>Sebep: {callback_reason}</li></ul><p>Talebinizi inceleyeceğiz ve yakında size geri döneceğiz.</p><p>{company_name} seçtiğiniz için teşekkürler.</p>',
                    'ru' => '<p>Привет {appointment_user_name},</p><p>Мы получили ваш запрос на обратный звонок.</p><p><strong>Детали обратного звонка:</strong></p><ul><li>Встреча: {appointment_name}</li><li>Запрошенная дата: {callback_date}</li><li>Запрошенное время: {callback_time}</li><li>Причина: {callback_reason}</li></ul><p>Мы рассмотрим ваш запрос и свяжемся с вами в ближайшее время.</p><p>Спасибо за выбор {company_name}.</p>',
                    'zh' => '<p>您好 {appointment_user_name}，</p><p>我们已收到您的回电请求。</p><p><strong>回电详情：</strong></p><ul><li>预约：{appointment_name}</li><li>请求日期：{callback_date}</li><li>请求时间：{callback_time}</li><li>原因：{callback_reason}</li></ul><p>我们将审核您的请求并尽快与您联系。</p><p>感谢您选择 {company_name}。</p>',
                ],
            ],
            'Appointment Status Update' => [
                'subject' => 'Appointment Status Update - {appointment_name}',
                'variables' => '{
                    "App Name": "app_name",
                    "Company Name": "company_name",
                    "App Url": "app_url",
                    "Appointment Name": "appointment_name",
                    "Appointment User Name": "appointment_user_name",
                    "Appointment User Email": "appointment_user_email",
                    "Appointment Date": "appointment_date",
                    "Appointment Time": "appointment_time",
                    "Appointment Number": "appointment_number",
                    "Appointment Status": "appointment_status"
                  }',
                  'lang' => [
                    'ar' => '<p>مرحبا {appointment_user_name}،</p><p>تم تحديث حالة موعدك.</p><p><strong>تفاصيل الموعد:</strong></p><ul><li>الموعد: {appointment_name}</li><li>التاريخ: {appointment_date}</li><li>الوقت: {appointment_time}</li><li>رقم الموعد: {appointment_number}</li><li>الحالة: {appointment_status}</li></ul><p>شكرا لاختيارك {company_name}.</p>',
                    'da' => '<p>Hej {appointment_user_name},</p><p>Din aftalestatus er blevet opdateret.</p><p><strong>Aftaledetaljer:</strong></p><ul><li>Aftale: {appointment_name}</li><li>Dato: {appointment_date}</li><li>Tid: {appointment_time}</li><li>Aftalenummer: {appointment_number}</li><li>Status: {appointment_status}</li></ul><p>Tak fordi du valgte {company_name}.</p>',
                    'de' => '<p>Hallo {appointment_user_name},</p><p>Ihr Terminstatus wurde aktualisiert.</p><p><strong>Termindetails:</strong></p><ul><li>Termin: {appointment_name}</li><li>Datum: {appointment_date}</li><li>Zeit: {appointment_time}</li><li>Terminnummer: {appointment_number}</li><li>Status: {appointment_status}</li></ul><p>Vielen Dank, dass Sie sich für {company_name} entschieden haben.</p>',
                    'en' => '<p>Hello {appointment_user_name},</p><p>Your appointment status has been updated.</p><p><strong>Appointment Details:</strong></p><ul><li>Appointment: {appointment_name}</li><li>Date: {appointment_date}</li><li>Time: {appointment_time}</li><li>Appointment Number: {appointment_number}</li><li>Status: {appointment_status}</li></ul><p>Thank you for choosing {company_name}.</p>',
                    'es' => '<p>Hola {appointment_user_name},</p><p>El estado de tu cita ha sido actualizado.</p><p><strong>Detalles de la Cita:</strong></p><ul><li>Cita: {appointment_name}</li><li>Fecha: {appointment_date}</li><li>Hora: {appointment_time}</li><li>Número de Cita: {appointment_number}</li><li>Estado: {appointment_status}</li></ul><p>Gracias por elegir {company_name}.</p>',
                    'fr' => '<p>Bonjour {appointment_user_name},</p><p>Le statut de votre rendez-vous a été mis à jour.</p><p><strong>Détails du Rendez-vous:</strong></p><ul><li>Rendez-vous: {appointment_name}</li><li>Date: {appointment_date}</li><li>Heure: {appointment_time}</li><li>Numéro de Rendez-vous: {appointment_number}</li><li>Statut: {appointment_status}</li></ul><p>Merci d\'avoir choisi {company_name}.</p>',
                    'it' => '<p>Ciao {appointment_user_name},</p><p>Lo stato del tuo appuntamento è stato aggiornato.</p><p><strong>Dettagli Appuntamento:</strong></p><ul><li>Appuntamento: {appointment_name}</li><li>Data: {appointment_date}</li><li>Ora: {appointment_time}</li><li>Numero Appuntamento: {appointment_number}</li><li>Stato: {appointment_status}</li></ul><p>Grazie per aver scelto {company_name}.</p>',
                    'ja' => '<p>こんにちは {appointment_user_name}、</p><p>あなたの予約ステータスが更新されました。</p><p><strong>予約詳細:</strong></p><ul><li>予約: {appointment_name}</li><li>日付: {appointment_date}</li><li>時間: {appointment_time}</li><li>予約番号: {appointment_number}</li><li>ステータス: {appointment_status}</li></ul><p>{company_name}をお選びいただきありがとうございます。</p>',
                    'nl' => '<p>Hallo {appointment_user_name},</p><p>Uw afspraakstatus is bijgewerkt.</p><p><strong>Afspraak Details:</strong></p><ul><li>Afspraak: {appointment_name}</li><li>Datum: {appointment_date}</li><li>Tijd: {appointment_time}</li><li>Afspraak Nummer: {appointment_number}</li><li>Status: {appointment_status}</li></ul><p>Bedankt voor het kiezen van {company_name}.</p>',
                    'pl' => '<p>Witaj {appointment_user_name},</p><p>Status Twojej wizyty został zaktualizowany.</p><p><strong>Szczegóły Wizyty:</strong></p><ul><li>Wizyta: {appointment_name}</li><li>Data: {appointment_date}</li><li>Czas: {appointment_time}</li><li>Numer Wizyty: {appointment_number}</li><li>Status: {appointment_status}</li></ul><p>Dziękujemy za wybranie {company_name}.</p>',
                    'pt' => '<p>Olá {appointment_user_name},</p><p>O status do seu agendamento foi atualizado.</p><p><strong>Detalhes do Agendamento:</strong></p><ul><li>Agendamento: {appointment_name}</li><li>Data: {appointment_date}</li><li>Hora: {appointment_time}</li><li>Número do Agendamento: {appointment_number}</li><li>Status: {appointment_status}</li></ul><p>Obrigado por escolher {company_name}.</p>',
                    'pt-BR' => '<p>Olá {appointment_user_name},</p><p>O status do seu agendamento foi atualizado.</p><p><strong>Detalhes do Agendamento:</strong></p><ul><li>Agendamento: {appointment_name}</li><li>Data: {appointment_date}</li><li>Hora: {appointment_time}</li><li>Número do Agendamento: {appointment_number}</li><li>Status: {appointment_status}</li></ul><p>Obrigado por escolher {company_name}.</p>',
                    'he' => '<p>שלום {appointment_user_name},</p><p>סטטוס הפגישה שלך עודכן.</p><p><strong>פרטי הפגישה:</strong></p><ul><li>פגישה: {appointment_name}</li><li>תאריך: {appointment_date}</li><li>שעה: {appointment_time}</li><li>מספר פגישה: {appointment_number}</li><li>סטטוס: {appointment_status}</li></ul><p>תודה שבחרת ב-{company_name}.</p>',
                    'tr' => '<p>Merhaba {appointment_user_name},</p><p>Randevu durumunuz güncellendi.</p><p><strong>Randevu Detayları:</strong></p><ul><li>Randevu: {appointment_name}</li><li>Tarih: {appointment_date}</li><li>Saat: {appointment_time}</li><li>Randevu Numarası: {appointment_number}</li><li>Durum: {appointment_status}</li></ul><p>{company_name} seçtiğiniz için teşekkürler.</p>',
                    'ru' => '<p>Привет {appointment_user_name},</p><p>Статус вашей встречи был обновлен.</p><p><strong>Детали встречи:</strong></p><ul><li>Встреча: {appointment_name}</li><li>Дата: {appointment_date}</li><li>Время: {appointment_time}</li><li>Номер встречи: {appointment_number}</li><li>Статус: {appointment_status}</li></ul><p>Спасибо за выбор {company_name}.</p>',
                    'zh' => '<p>您好 {appointment_user_name}，</p><p>您的预约状态已更新。</p><p><strong>预约详情：</strong></p><ul><li>预约：{appointment_name}</li><li>日期：{appointment_date}</li><li>时间：{appointment_time}</li><li>预约号码：{appointment_number}</li><li>状态：{appointment_status}</li></ul><p>感谢您选择 {company_name}。</p>',
                ],
            ],
            'Appointment Callback Status Update' => [
                'subject' => 'Callback Status Update - {appointment_name}',
                'variables' => '{
                    "App Name": "app_name",
                    "Company Name": "company_name",
                    "App Url": "app_url",
                    "Appointment Name": "appointment_name",
                    "Appointment User Name": "appointment_user_name",
                    "Appointment User Email": "appointment_user_email",
                    "Callback Date": "callback_date",
                    "Callback Time": "callback_time",
                    "Callback Reason": "callback_reason",
                    "Callback Status": "callback_status"
                  }',
                  'lang' => [
                    'ar' => '<p>مرحبا {appointment_user_name}،</p><p>تم تحديث حالة طلب الاتصال المرتد الخاص بك.</p><p><strong>تفاصيل الاتصال المرتد:</strong></p><ul><li>الموعد: {appointment_name}</li><li>التاريخ المطلوب: {callback_date}</li><li>الوقت المطلوب: {callback_time}</li><li>السبب: {callback_reason}</li><li>الحالة: {callback_status}</li></ul><p>شكرا لاختيارك {company_name}.</p>',
                    'da' => '<p>Hej {appointment_user_name},</p><p>Din tilbagekald status er blevet opdateret.</p><p><strong>Tilbagekald Detaljer:</strong></p><ul><li>Aftale: {appointment_name}</li><li>Ønsket Dato: {callback_date}</li><li>Ønsket Tid: {callback_time}</li><li>Årsag: {callback_reason}</li><li>Status: {callback_status}</li></ul><p>Tak fordi du valgte {company_name}.</p>',
                    'de' => '<p>Hallo {appointment_user_name},</p><p>Ihr Rückruf-Status wurde aktualisiert.</p><p><strong>Rückruf-Details:</strong></p><ul><li>Termin: {appointment_name}</li><li>Gewünschtes Datum: {callback_date}</li><li>Gewünschte Zeit: {callback_time}</li><li>Grund: {callback_reason}</li><li>Status: {callback_status}</li></ul><p>Vielen Dank, dass Sie sich für {company_name} entschieden haben.</p>',
                    'en' => '<p>Hello {appointment_user_name},</p><p>Your callback request status has been updated.</p><p><strong>Callback Details:</strong></p><ul><li>Appointment: {appointment_name}</li><li>Requested Date: {callback_date}</li><li>Requested Time: {callback_time}</li><li>Reason: {callback_reason}</li><li>Status: {callback_status}</li></ul><p>Thank you for choosing {company_name}.</p>',
                    'es' => '<p>Hola {appointment_user_name},</p><p>El estado de tu solicitud de devolución de llamada ha sido actualizado.</p><p><strong>Detalles de Devolución de Llamada:</strong></p><ul><li>Cita: {appointment_name}</li><li>Fecha Solicitada: {callback_date}</li><li>Hora Solicitada: {callback_time}</li><li>Razón: {callback_reason}</li><li>Estado: {callback_status}</li></ul><p>Gracias por elegir {company_name}.</p>',
                    'fr' => '<p>Bonjour {appointment_user_name},</p><p>Le statut de votre demande de rappel a été mis à jour.</p><p><strong>Détails du Rappel:</strong></p><ul><li>Rendez-vous: {appointment_name}</li><li>Date Demandée: {callback_date}</li><li>Heure Demandée: {callback_time}</li><li>Raison: {callback_reason}</li><li>Statut: {callback_status}</li></ul><p>Merci d\'avoir choisi {company_name}.</p>',
                    'it' => '<p>Ciao {appointment_user_name},</p><p>Lo stato della tua richiesta di richiamata è stato aggiornato.</p><p><strong>Dettagli Richiamata:</strong></p><ul><li>Appuntamento: {appointment_name}</li><li>Data Richiesta: {callback_date}</li><li>Ora Richiesta: {callback_time}</li><li>Motivo: {callback_reason}</li><li>Stato: {callback_status}</li></ul><p>Grazie per aver scelto {company_name}.</p>',
                    'ja' => '<p>こんにちは {appointment_user_name}、</p><p>コールバックリクエストのステータスが更新されました。</p><p><strong>コールバック詳細:</strong></p><ul><li>予約: {appointment_name}</li><li>希望日: {callback_date}</li><li>希望時間: {callback_time}</li><li>理由: {callback_reason}</li><li>ステータス: {callback_status}</li></ul><p>{company_name}をお選びいただきありがとうございます。</p>',
                    'nl' => '<p>Hallo {appointment_user_name},</p><p>Uw terugbelverzoek status is bijgewerkt.</p><p><strong>Terugbel Details:</strong></p><ul><li>Afspraak: {appointment_name}</li><li>Gewenste Datum: {callback_date}</li><li>Gewenste Tijd: {callback_time}</li><li>Reden: {callback_reason}</li><li>Status: {callback_status}</li></ul><p>Bedankt voor het kiezen van {company_name}.</p>',
                    'pl' => '<p>Witaj {appointment_user_name},</p><p>Status Twojej prośby o oddzwonienie został zaktualizowany.</p><p><strong>Szczegóły Oddzwonienia:</strong></p><ul><li>Wizyta: {appointment_name}</li><li>Żądana Data: {callback_date}</li><li>Żądany Czas: {callback_time}</li><li>Powód: {callback_reason}</li><li>Status: {callback_status}</li></ul><p>Dziękujemy za wybranie {company_name}.</p>',
                    'pt' => '<p>Olá {appointment_user_name},</p><p>O status da sua solicitação de retorno de chamada foi atualizado.</p><p><strong>Detalhes do Retorno:</strong></p><ul><li>Agendamento: {appointment_name}</li><li>Data Solicitada: {callback_date}</li><li>Hora Solicitada: {callback_time}</li><li>Motivo: {callback_reason}</li><li>Status: {callback_status}</li></ul><p>Obrigado por escolher {company_name}.</p>',
                    'pt-BR' => '<p>Olá {appointment_user_name},</p><p>O status da sua solicitação de retorno de chamada foi atualizado.</p><p><strong>Detalhes do Retorno:</strong></p><ul><li>Agendamento: {appointment_name}</li><li>Data Solicitada: {callback_date}</li><li>Hora Solicitada: {callback_time}</li><li>Motivo: {callback_reason}</li><li>Status: {callback_status}</li></ul><p>Obrigado por escolher {company_name}.</p>',
                    'he' => '<p>שלום {appointment_user_name},</p><p>סטטוס בקשת החזרת הקריאה שלך עודכן.</p><p><strong>פרטי החזרת קריאה:</strong></p><ul><li>פגישה: {appointment_name}</li><li>תאריך מבוקש: {callback_date}</li><li>שעה מבוקשת: {callback_time}</li><li>סיבה: {callback_reason}</li><li>סטטוס: {callback_status}</li></ul><p>תודה שבחרת ב-{company_name}.</p>',
                    'tr' => '<p>Merhaba {appointment_user_name},</p><p>Geri arama talebinizin durumu güncellendi.</p><p><strong>Geri Arama Detayları:</strong></p><ul><li>Randevu: {appointment_name}</li><li>İstenen Tarih: {callback_date}</li><li>İstenen Saat: {callback_time}</li><li>Sebep: {callback_reason}</li><li>Durum: {callback_status}</li></ul><p>{company_name} seçtiğiniz için teşekkürler.</p>',
                    'ru' => '<p>Привет {appointment_user_name},</p><p>Статус вашего запроса на обратный звонок был обновлен.</p><p><strong>Детали обратного звонка:</strong></p><ul><li>Встреча: {appointment_name}</li><li>Запрошенная дата: {callback_date}</li><li>Запрошенное время: {callback_time}</li><li>Причина: {callback_reason}</li><li>Статус: {callback_status}</li></ul><p>Спасибо за выбор {company_name}.</p>',
                    'zh' => '<p>您好 {appointment_user_name}，</p><p>您的回电请求状态已更新。</p><p><strong>回电详情：</strong></p><ul><li>预约：{appointment_name}</li><li>请求日期：{callback_date}</li><li>请求时间：{callback_time}</li><li>原因：{callback_reason}</li><li>状态：{callback_status}</li></ul><p>感谢您选择 {company_name}。</p>',
                ],
            ],
        ];

        foreach($emailTemplate as $eTemp)
        {
            $table = EmailTemplate::where('name',$eTemp)->where('module_name','Appointment')->exists();
            if(!$table)
            {
                $emailtemplate = EmailTemplate::create([
                    'name' => $eTemp,
                    'from' => !empty(env('APP_NAME')) ? env('APP_NAME') : 'WorkDo Dash',
                    'module_name' => 'Appointment',
                    'created_by' => $admin->id,
                    'creator_id' => $admin->id,
                ]);

                foreach($defaultTemplate[$eTemp]['lang'] as $lang => $content)
                {
                    EmailTemplateLang::create([
                        'parent_id' => $emailtemplate->id,
                        'lang' => $lang,
                        'subject' => $defaultTemplate[$eTemp]['subject'],
                        'variables' => $defaultTemplate[$eTemp]['variables'],
                        'content' => $content,
                    ]);
                }
            }
        }
    }
}
