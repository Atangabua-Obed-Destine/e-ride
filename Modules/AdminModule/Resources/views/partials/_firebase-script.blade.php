<audio id="myAudio">
    <source src="{{dynamicAsset('public/assets/admin-module/sound/safety-alert.mp3')}}" type="audio/mpeg">
</audio>
<script>
    "use strict"
    let audio = document.getElementById("myAudio");

    let isPlaying = false;

    // Add an event listener to replay the audio when it ends
    audio.addEventListener("ended", function () {
        if (isPlaying) {
            audio.currentTime = 0;
            audio.play();
        }
    });

    function playAudio() {
        isPlaying = true;
        audio.play().catch(function (error) {
            console.error("Error playing audio:", error);
        });
    }

    function stopAudio() {
        isPlaying = false;
        audio.pause();
        audio.currentTime = 0; // Reset to the start
    }


    // Initialize Firebase
    firebase.initializeApp({
        apiKey: "{{ businessConfig(key: 'api_key',settingsType: NOTIFICATION_SETTINGS)?->value ?? '' }}",
        authDomain: "{{ businessConfig(key: 'auth_domain',settingsType: NOTIFICATION_SETTINGS)?->value ?? '' }}",
        projectId: "{{ businessConfig(key: 'project_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '' }}",
        storageBucket: "{{ businessConfig(key: 'storage_bucket',settingsType: NOTIFICATION_SETTINGS)?->value ?? '' }}",
        messagingSenderId: "{{ businessConfig(key: 'messaging_sender_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '' }}",
        appId: "{{ businessConfig(key: 'app_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '' }}",
        measurementId: "{{ businessConfig(key: 'measurement_id',settingsType: NOTIFICATION_SETTINGS)?->value ?? '' }}",
    });


    const messaging = firebase.messaging();

    function startFCM() {
        messaging
            .requestPermission()
            .then(function () {
                return messaging.getToken();
            })
            .then(function (token) {
                // console.log('FCM Token:', token);
                // Send the token to your backend to subscribe to topic
                subscribeTokenToBackend(token, 'admin_safety_alert_notification');
            }).catch(function (error) {
            console.error('Error getting permission or token:', error);
        });
    }

    function subscribeTokenToBackend(token, topic) {
        fetch('{{route('admin.subscribe-topic')}}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({token: token, topic: topic})
        }).then(response => {
            if (response.status < 200 || response.status >= 400) {
                return response.text().then(text => {
                    throw new Error(`Error subscribing to topic: ${response.status} - ${text}`);
                });
            }
        }).catch(error => {
            console.error('Subscription error:', error);
        });
    }

    messaging.onMessage(function (payload) {
        if (payload.data) {
            safetyAlertNotification(payload.data);
            playAudio();
            let safetyAlertIconMap = document.getElementsByClassName('safety-alert-icon-map');
            let zoneMessageDiv = document.getElementsByClassName('get-zone-message');
            getSafetyAlerts();
            if (zoneMessageDiv) {
                getZoneMessage();
            }
            if (safetyAlertIconMap) {
                fetchSafetyAlertIcon(true);
            }
            $('.zone-message').removeClass('invisible');
            sessionStorage.removeItem('showZoneMessage');
        }
    })
    startFCM();

    function fetchSafetyAlertIcon(condition = false) {
        let url = "{{ route('admin.fleet-map-safety-alert-icon-in-map') }}";
        $.ajax({
            url: url,
            method: 'GET',
            success: function (response) {
                $('.safety-alert-icon-map').empty().html(response);
                if (condition) {
                    if ($('#safetyAlertMapIcon').length) {
                        $('#safetyAlertMapIcon').addClass('d-none');
                    }
                    if ($('#newSafetyAlertMapIcon').length) {
                        $('#newSafetyAlertMapIcon').removeClass('d-none');
                    }
                }

                $('.show-safety-alert-user-details').on('click', function () {
                    localStorage.setItem('safetyAlertUserDetailsStatus', true);
                });
            }
        })
    }

    function getZoneMessage() {
        let url = "{{ route('admin.fleet-map-zone-message') }}";
        $.ajax({
            url: url,
            method: 'GET',
            success: function (response) {
                $('.get-zone-message').empty().html(response);
                $('.zone-message-hide').on('click', function () {
                    $('.zone-message').addClass('invisible');
                    sessionStorage.setItem('showZoneMessage', 'false');
                });
            }
        })

    }

</script>
