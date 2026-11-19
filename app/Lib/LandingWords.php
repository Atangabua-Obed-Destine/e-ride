<?php

namespace App\Lib;

/**
 * The home page's words (eride.innovakickstarter.com), English and French. The page mixes the two
 * (and a little Pidgin) on purpose, like the app's onboarding: some eyebrows stay in the other language.
 */
class LandingWords
{
    public const LANGS = ['en', 'fr'];

    protected const EN = [
        'title' => 'E-Ride: everything you need, in one app',
        'meta' => 'Rides, food, parcels, bus tickets, hotels, jobs and more in one app, paid with MTN MoMo or Orange Money. Bamenda first, then all of Cameroon.',
        'nav_services' => 'Services', 'nav_bus' => 'Bus tickets', 'nav_safe' => 'Safety', 'nav_partners' => 'Partners',
        'get_app' => 'Get the app', 'menu' => 'Menu', 'switch_lang' => 'Français',
        'hero_eyebrow' => 'BIENVENUE SUR E-RIDE',
        'hero_title' => 'Everything you need, in one app.',
        'hero_body' => 'Rides, food, parcels, bus tickets, hotels, jobs and more, right where you are in Bamenda.',
        'play' => 'Get it on Google Play', 'bus_cta' => 'Book a bus seat online',
        'chip_approved' => 'Drivers approved by E-Ride', 'chip_share' => 'Trips shared live',
        'phone_alt' => 'The E-Ride app home: Where to? search, Ride, Food and Shops, Parcel, Market and Jobs',
        'band' => ['Rides', 'Chop', 'Colis', 'Bus', 'Hôtels', 'Market', 'Jobs', 'Homes', 'On est ensemble!'],
        's_eyebrow' => 'TOUT EN UN', 's_title' => 'Ten doors, one account',
        's_lead' => 'One account for all ten. Sign in once, pay every one of them with MTN MoMo or Orange Money.',
        'ride_eyebrow' => 'RIDE · BOOK FOR LATER · EMERGENCY RIDE', 'ride_title' => 'Allez partout en ville', 'ride_pitch' => 'A driver to anywhere in town, in a few taps.',
        'ride_pts' => ['See the fare before you book', 'Ride now, or book from 1 hour to 3 days ahead', 'Follow your driver on the map and send the trip to family', 'Emergency ride: straight to a clinic, hospital or pharmacy'],
        'food_eyebrow' => 'FOOD & SHOPS', 'food_title' => 'Your chop go reach your door', 'food_pitch' => 'Restaurants and shops near you, delivered by E-Ride riders.',
        'food_pts' => ['The restaurant confirms your order before you pay', "Today's menu of each place, with its prices", 'Follow the rider all the way to your door'],
        'parcel_title' => 'Colis · Parcels', 'parcel_pitch' => 'Send a parcel anywhere in town.',
        'parcel_pts' => ['A rider collects it where you are', 'Follow it live until it arrives', 'Rate the rider at delivery'],
        'move_title' => 'Move a house or a shop', 'move_pitch' => 'Book a truck for your load.',
        'move_pts' => ['Say what you are moving, add photos', 'A small deposit books the truck', 'Agree the price with the driver, pay them directly'],
        'bus_eyebrow' => 'BUS TICKETS · ALSO ON THE WEB', 'bus_title' => 'Your seat on the bus, before you leave home', 'bus_pitch' => 'Verified agencies from Bamenda to Douala, Yaoundé, Buea and more.',
        'bus_pts' => ['Pick your seat on the real seat map', 'The agency accepts, then you pay with Mobile Money within 15 minutes', 'Show the QR ticket at boarding, or print it', 'Need a ride to the agency? Book it from your ticket'],
        'hotel_eyebrow' => 'HÔTELS · GUEST HOUSES', 'hotel_title' => 'Ask for a room, then ride there', 'hotel_pitch' => 'See which rooms are free for your dates.',
        'hotel_pts' => ['Chat with the reception, or call them', 'They keep a room for you until your arrival time', 'Nothing to pay in the app: you pay the hotel'],
        'explore_title' => 'Explore', 'explore_pitch' => 'Places near you, open now.', 'explore_pts' => ['Pharmacies on duty, hospitals, banks', 'Call, WhatsApp or ride there in one tap'],
        'market_title' => 'Market', 'market_pitch' => 'Buy and sell near you.', 'market_pts' => ['Open a free shop, share one link on WhatsApp', 'Chat in E-Ride, see the item before you pay'],
        'jobs_title' => 'Jobs', 'jobs_pitch' => 'Verified jobs near you.', 'jobs_pts' => ['Your Work Card, with a voice intro', 'Apply, then ride to the interview'],
        'homes_title' => 'Homes', 'homes_pitch' => 'Rooms, studios, offices and shops to rent.', 'homes_pts' => ['E-Ride Verified places come first', 'Never pay before you visit'],
        'day_eyebrow' => 'UN JOUR À BAMENDA', 'day_title' => 'One day, one app', 'day_lead' => 'Tap a time and follow the day.',
        'day' => [
            ['06:45', 'Ride to work', 'Nkwen to Commercial Avenue', 'RIDE', 'Your driver is on the way', 'Book from home, see the fare first, pay with MoMo when you arrive.', 'ride'],
            ['12:30', 'Chop at the office', 'From a restaurant near you', 'FOOD & SHOPS', 'Your chop go reach your desk', 'The restaurant confirms, you pay, a rider brings it hot.', 'food'],
            ['15:00', 'A parcel for Mami', 'Commercial Avenue to Up Station', 'COLIS', 'Send it now, follow it live', 'The rider picks it up and you watch the trip to the door.', 'parcel'],
            ['18:00', 'Bus seat for tomorrow', 'Bamenda to Douala, 06:00', 'BUS', 'Seat 7, paid, done', 'Pick the seat on the real bus, pay with Mobile Money, show the QR at boarding.', 'bus'],
            ['21:00', 'A room for your guest', 'A hotel near Up Station', 'HÔTELS', 'The reception keeps the room', 'Ask in a chat, the hotel keeps a room until your time, then ride there.', 'hotel'],
        ],
        'm_eyebrow' => 'WHEN IT MATTERS', 'm_title' => 'Fast to the clinic. Easy to Douala.',
        'er_eyebrow' => "EMERGENCY RIDE · COURSE D'URGENCE", 'er_title' => 'A clinic, a hospital or a pharmacy, fast',
        'er_body' => 'A driver takes you straight to the clinic, hospital or pharmacy you choose. Send the live trip to up to 3 people you trust.',
        'er_note' => 'This is not an ambulance. If someone is in danger, call the emergency services.',
        'bw_eyebrow' => 'BUS TICKETS · ON THE WEB TOO', 'bw_title' => 'Pick your seat, pay with MoMo',
        'bw_body' => 'The real seat map of verified agencies. No app needed to book: do it on the website.', 'bw_body_app' => 'The real seat map of verified agencies, in the E-Ride app.',
        'bw_cta' => 'Book a bus seat', 'bus_alt' => 'The E-Ride bus screen: Bamenda to anywhere, popular routes',
        'safe_eyebrow' => 'PAYER · SÉCURITÉ', 'safe_title' => 'Simple to pay, safe to use',
        'safe' => [
            ['Mobile Money only', 'MTN MoMo or Orange Money, approved on your phone. One account for every service.'],
            ['Trusted contacts', 'During a trip, send a live link on WhatsApp to up to 3 people you trust.'],
            ['Approved drivers', 'Every driver is approved by E-Ride before their first trip.'],
            ['Safety alert', 'Something feels wrong on a trip? One tap sends an alert to E-Ride.'],
        ],
        'together' => 'On est ensemble',
        'p_eyebrow' => 'GROW WITH E-RIDE', 'p_title' => 'Drive, sell, host, travel with us',
        'partners' => [
            ['DRIVERS', 'Drive with E-Ride', 'Your car or motorbike, your hours. Sign up in the E-Ride Driver app.', 'Get the Driver app', 'driver'],
            ['RESTAURANTS & SHOPS', 'Sell on Food & Shops', 'Your menu in the app, orders on your own portal, E-Ride riders deliver.', 'List your place', 'I want to list my restaurant or shop on E-Ride Food & Shops.'],
            ['BUS AGENCIES', 'Fill every seat', 'Your timetable and seat map online, payments to your own Mobile Money.', 'Bring your agency', 'I want to bring my bus agency to E-Ride.'],
            ['HOTELS', 'Free for hotels', 'Guests ask your reception in a chat and ride straight to you.', 'Add your hotel', 'I want to add my hotel or guest house to E-Ride.'],
        ],
        'get_eyebrow' => "C'EST PARTI", 'get_title' => 'Get E-Ride. Everything else follows.', 'get_body' => 'Free on Google Play. Bamenda first, then all of Cameroon.',
        'scan' => 'Scan with your phone', 'qr_alt' => 'QR code: E-Ride on Google Play',
        'foot_tag' => 'E-Ride by I-NNOVA. Rides, delivery and more, Cameroon.',
        'contact' => 'Contact', 'links' => 'E-Ride', 'about' => 'About us', 'privacy' => 'Privacy policy', 'terms' => 'Terms', 'contact_us' => 'Contact us', 'blog' => 'Blog',
    ];

    protected const FR = [
        'title' => "E-Ride : tout ce qu'il vous faut, dans une appli",
        'meta' => 'Courses, repas, colis, billets de bus, hôtels, emplois et plus dans une appli, payés avec MTN MoMo ou Orange Money. Bamenda d\'abord, puis tout le Cameroun.',
        'nav_services' => 'Services', 'nav_bus' => 'Billets de bus', 'nav_safe' => 'Sécurité', 'nav_partners' => 'Partenaires',
        'get_app' => "Avoir l'appli", 'menu' => 'Menu', 'switch_lang' => 'English',
        'hero_eyebrow' => 'WELCOME TO E-RIDE',
        'hero_title' => "Tout ce qu'il vous faut, dans une appli.",
        'hero_body' => 'Courses, repas, colis, billets de bus, hôtels, emplois et plus, là où vous êtes à Bamenda.',
        'play' => 'Disponible sur Google Play', 'bus_cta' => 'Réserver une place de bus',
        'chip_approved' => 'Chauffeurs validés par E-Ride', 'chip_share' => 'Trajets partagés en direct',
        'phone_alt' => "L'accueil de l'appli E-Ride : recherche Où allez-vous ?, Course, Repas et boutiques, Colis, Market et Jobs",
        'band' => ['Courses', 'Chop', 'Colis', 'Bus', 'Hôtels', 'Market', 'Jobs', 'Logements', 'On est ensemble!'],
        's_eyebrow' => 'ALL IN ONE', 's_title' => 'Dix portes, un seul compte',
        's_lead' => 'Un seul compte pour les dix. Connectez-vous une fois, payez chacun avec MTN MoMo ou Orange Money.',
        'ride_eyebrow' => "COURSE · PLUS TARD · COURSE D'URGENCE", 'ride_title' => 'Allez partout en ville', 'ride_pitch' => "Un chauffeur pour aller n'importe où en ville, en quelques gestes.",
        'ride_pts' => ['Voyez le prix avant de réserver', "Partez maintenant, ou réservez de 1 heure à 3 jours à l'avance", 'Suivez votre chauffeur sur la carte et envoyez le trajet à vos proches', "Course d'urgence : direct à une clinique, un hôpital ou une pharmacie"],
        'food_eyebrow' => 'REPAS & BOUTIQUES', 'food_title' => 'Your chop go reach your door', 'food_pitch' => 'Les restaurants et boutiques près de vous, livrés par les livreurs E-Ride.',
        'food_pts' => ['Le restaurant confirme votre commande avant que vous payiez', 'Le menu du jour de chaque endroit, avec ses prix', "Suivez le livreur jusqu'à votre porte"],
        'parcel_title' => 'Colis', 'parcel_pitch' => "Envoyez un colis n'importe où en ville.",
        'parcel_pts' => ['Un livreur le récupère là où vous êtes', "Suivez-le en direct jusqu'à l'arrivée", 'Notez le livreur à la livraison'],
        'move_title' => 'Déménager une maison ou une boutique', 'move_pitch' => 'Réservez un camion pour vos biens.',
        'move_pts' => ['Dites ce que vous déplacez, ajoutez des photos', 'Un petit acompte réserve le camion', 'Fixez le prix avec le chauffeur, payez-le directement'],
        'bus_eyebrow' => 'BILLETS DE BUS · AUSSI SUR LE WEB', 'bus_title' => 'Votre place dans le bus, avant de quitter la maison', 'bus_pitch' => 'Des agences vérifiées de Bamenda vers Douala, Yaoundé, Buea et plus.',
        'bus_pts' => ['Choisissez votre place sur le vrai plan du bus', "L'agence accepte, puis vous payez avec Mobile Money en 15 minutes", "Montrez le billet QR à l'embarquement, ou imprimez-le", "Besoin d'une course jusqu'à l'agence ? Réservez-la depuis votre billet"],
        'hotel_eyebrow' => 'HÔTELS · AUBERGES', 'hotel_title' => "Demandez une chambre, puis allez-y", 'hotel_pitch' => 'Voyez les chambres libres pour vos dates.',
        'hotel_pts' => ['Écrivez à la réception, ou appelez-la', "Elle vous garde une chambre jusqu'à votre heure d'arrivée", "Rien à payer dans l'appli : vous payez l'hôtel"],
        'explore_title' => 'Explorer', 'explore_pitch' => 'Les lieux près de vous, ouverts maintenant.', 'explore_pts' => ['Pharmacies de garde, hôpitaux, banques', "Appelez, écrivez sur WhatsApp ou allez-y d'un geste"],
        'market_title' => 'Market', 'market_pitch' => 'Achetez et vendez près de chez vous.', 'market_pts' => ['Ouvrez une boutique gratuite, partagez un lien sur WhatsApp', "Discutez dans E-Ride, voyez l'article avant de payer"],
        'jobs_title' => 'Jobs', 'jobs_pitch' => 'Des emplois vérifiés près de vous.', 'jobs_pts' => ['Votre Carte Travail, avec une présentation vocale', "Postulez, puis allez à l'entretien en course"],
        'homes_title' => 'Logements', 'homes_pitch' => 'Chambres, studios, bureaux et boutiques à louer.', 'homes_pts' => ["Les lieux vérifiés par E-Ride d'abord", 'Ne payez jamais avant la visite'],
        'day_eyebrow' => 'ONE DAY IN BAMENDA', 'day_title' => 'Une journée, une appli', 'day_lead' => 'Touchez une heure et suivez la journée.',
        'day' => [
            ['06:45', 'La course du matin', 'De Nkwen à Commercial Avenue', 'COURSE', 'Votre chauffeur arrive', "Réservez de chez vous, voyez le prix d'abord, payez avec MoMo à l'arrivée."],
            ['12:30', 'Le chop au bureau', "D'un restaurant près de vous", 'REPAS', 'Your chop go reach your desk', 'Le restaurant confirme, vous payez, un livreur vous l\'apporte chaud.'],
            ['15:00', 'Un colis pour Mami', 'De Commercial Avenue à Up Station', 'COLIS', 'Envoyez-le, suivez-le en direct', "Le livreur le récupère et vous suivez le trajet jusqu'à la porte."],
            ['18:00', 'Une place de bus pour demain', 'Bamenda à Douala, 06:00', 'BUS', 'Place 7, payée, c\'est fait', "Choisissez la place dans le vrai bus, payez avec Mobile Money, montrez le QR à l'embarquement."],
            ['21:00', 'Une chambre pour votre invité', "Un hôtel près d'Up Station", 'HÔTELS', 'La réception garde la chambre', "Demandez dans le chat, l'hôtel garde une chambre jusqu'à votre heure, puis allez-y."],
        ],
        'm_eyebrow' => 'QUAND ÇA COMPTE', 'm_title' => 'Vite à la clinique. Facile jusqu\'à Douala.',
        'er_eyebrow' => "COURSE D'URGENCE · EMERGENCY RIDE", 'er_title' => 'Une clinique, un hôpital ou une pharmacie, vite',
        'er_body' => "Un chauffeur vous emmène directement à la clinique, à l'hôpital ou à la pharmacie de votre choix. Envoyez le trajet en direct à 3 personnes de confiance.",
        'er_note' => "Ce n'est pas une ambulance. Si quelqu'un est en danger, appelez les secours.",
        'bw_eyebrow' => 'BILLETS DE BUS · AUSSI SUR LE WEB', 'bw_title' => 'Choisissez votre place, payez avec MoMo',
        'bw_body' => "Le vrai plan du bus des agences vérifiées. Pas besoin de l'appli pour réserver : faites-le sur le site.", 'bw_body_app' => "Le vrai plan du bus des agences vérifiées, dans l'appli E-Ride.",
        'bw_cta' => 'Réserver une place', 'bus_alt' => "L'écran bus d'E-Ride : Bamenda vers partout, trajets populaires",
        'safe_eyebrow' => 'PAY · SAFETY', 'safe_title' => 'Simple pour payer, sûr à utiliser',
        'safe' => [
            ['Mobile Money uniquement', 'MTN MoMo ou Orange Money, validé sur votre téléphone. Un seul compte pour tous les services.'],
            ['Contacts de confiance', 'Pendant un trajet, envoyez un lien en direct sur WhatsApp à 3 personnes de confiance.'],
            ['Chauffeurs validés', 'Chaque chauffeur est validé par E-Ride avant sa première course.'],
            ['Alerte sécurité', "Quelque chose ne va pas pendant un trajet ? Un geste alerte E-Ride."],
        ],
        'together' => 'On est ensemble',
        'p_eyebrow' => 'GRANDIR AVEC E-RIDE', 'p_title' => 'Conduisez, vendez, accueillez, voyagez avec nous',
        'partners' => [
            ['CHAUFFEURS', 'Conduire avec E-Ride', "Votre voiture ou votre moto, vos horaires. Inscrivez-vous dans l'appli E-Ride Driver.", "Avoir l'appli Driver", 'driver'],
            ['RESTAURANTS & BOUTIQUES', 'Vendre sur Repas & boutiques', "Votre menu dans l'appli, les commandes sur votre portail, les livreurs E-Ride livrent.", 'Ajouter mon commerce', 'Je veux ajouter mon restaurant ou ma boutique sur E-Ride Repas & boutiques.'],
            ['AGENCES DE BUS', 'Remplissez chaque place', 'Vos horaires et votre plan de bus en ligne, les paiements sur votre propre Mobile Money.', 'Ajouter mon agence', 'Je veux ajouter mon agence de bus sur E-Ride.'],
            ['HÔTELS', 'Gratuit pour les hôtels', 'Les clients écrivent à votre réception et viennent directement chez vous.', 'Ajouter mon hôtel', 'Je veux ajouter mon hôtel ou mon auberge sur E-Ride.'],
        ],
        'get_eyebrow' => "LET'S GO", 'get_title' => 'Prenez E-Ride. Le reste suit.', 'get_body' => "Gratuit sur Google Play. Bamenda d'abord, puis tout le Cameroun.",
        'scan' => 'Scannez avec votre téléphone', 'qr_alt' => 'Code QR : E-Ride sur Google Play',
        'foot_tag' => 'E-Ride par I-NNOVA. Courses, livraisons et plus, Cameroun.',
        'contact' => 'Contact', 'links' => 'E-Ride', 'about' => 'À propos', 'privacy' => 'Confidentialité', 'terms' => 'Conditions', 'contact_us' => 'Nous contacter', 'blog' => 'Blog',
    ];

    public function __construct(public readonly string $lang = 'en')
    {
    }

    public static function for(?string $lang): self
    {
        return new self(in_array($lang, self::LANGS, true) ? $lang : 'en');
    }

    /** A word, a list or a table (the day, the partners): whatever the key holds. */
    public function __invoke(string $key): mixed
    {
        return ($this->lang === 'fr' ? self::FR : self::EN)[$key] ?? self::EN[$key] ?? $key;
    }
}
