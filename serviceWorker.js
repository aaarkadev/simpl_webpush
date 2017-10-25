var notificationclick_url='https://www.example.ru/?utm_source=webpush&utm_medium=webpush&utm_campaign=webpushdefault';


self.addEventListener('install', function(event) {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', function(event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    if (!(self.Notification && self.Notification.permission == 'granted')) {
        return;
    }


    event.waitUntil(
        self.clients.matchAll({
            includeUncontrolled: true
        }).then(function(clientList) {
            var focused = clientList.some(function(client) {
                return client.focused;
            });

            var page_tab_status='closed';
            if (focused) {
                page_tab_status='opened';
            } else if (clientList.length > 0) {
                page_tab_status='hidden';
            } else {
                page_tab_status='closed';
            }

            return new Promise(function(resolve, reject) {
                resolve( {'page_tab_status':page_tab_status,'event':event} );
            });


        }).then(function(obj) {
            return getNotificationData(obj.event,obj.page_tab_status);
        }).catch(function(err) {
//        console.log('sw catch:^(', err);
        })
    );

});


self.addEventListener('pushsubscriptionchange', function(event) {

    if(typeof(self['webpushServerKey'])=='undefined')
        self['webpushServerKey']= "BJs/+11111111111111111111111111111111111111111111111111111111111111111111111111111111KQ=";

    event.waitUntil(
        self.registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(window['webpushServerKey'])
        })
            .then(function(subscription) {

                if(!subscription || !subscription.endpoint)
                    return false;

                var key_val='';
                var token_val='';
                if(typeof(subscription.getKey)!='undefined') {
                    key_val= btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh'))));
                    token_val=btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth'))));
                }

                return fetch('/ajax_webpush.php?registr_endpoint=1', {
                    method: 'post',
                    headers: {
                        'Content-type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        endpoint: subscription.endpoint,
                        key: key_val,
                        token: token_val
                    })
                });

            }).catch(function(err) {
//        console.log('sw catch:^(', err);
        })
    );

});


self.addEventListener('notificationclick', function(event) {
    if(event.notification)
        event.notification.close();

    var lnk='https://www.example.ru/';
    if(typeof(notificationclick_url)!='undefined' && notificationclick_url)
        lnk=notificationclick_url;

    event.waitUntil( self.clients.openWindow(lnk) );
 

});


function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - base64String.length % 4) % 4);
    var base64 = (base64String + padding)
        .replace(/\-/g, '+')
        .replace(/_/g, '/');

    var rawData = window.atob(base64);
    var outputArray = new Uint8Array(rawData.length);
    var i=0;

    for (i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

function getNotificationData(event,page_tab_status) {
    if (event.data) {
        var message = event.data.text();
        return sendNotification({'body':message,'page_tab_status':page_tab_status});
    } else {

        return self.registration.pushManager.getSubscription()
            .then(function(subscription) {

                return new Promise(function(resolve, reject) {
                    //setTimeout(function() {
                    resolve((subscription?subscription.endpoint:false));
                    //}, (500+parseInt(Math.random()*500)));
                });


            })
            .then(function(endpoint) {

                fetch('/ajax_webpush.php?get_endpoint_msg=1', {
                    method: 'post',
                    headers: {
                        'Content-type': 'application/json'
                    },
                    body: JSON.stringify({'endpoint': endpoint,'page_tab_status':page_tab_status})
                })
                    .then(function(response) {
                        if (response.status !== 200) throw new Error("responseStatus not 200");
                        return response.text();
                    })
                    .then(function(payload) {
                        var sendObj={'page_tab_status':page_tab_status};

                        if(payload) {
                            sendObj=JSON.parse(payload);
                        }
                        return sendNotification(sendObj);
                    }).catch(function(err) {
                    return sendNotification({'page_tab_status':page_tab_status});
                })

            }).catch(function(err) {
                return sendNotification({'page_tab_status':page_tab_status});
            })
    }
}

function sendNotification(sendObj) {

    var title='example.ru';
    var params={
        'icon':'/favicon.ico',
        'tag':'news',
        'body':'Обратите внимание'
    };

    for(i in sendObj) {
        if(typeof(params[i])!='undefined') {
            params[i]=sendObj[i];
        }
    }
    if(typeof(sendObj['title'])!='undefined') {
        title=sendObj['title'];
    }

    if(typeof(notificationclick_url)!='undefined' && notificationclick_url && typeof(sendObj['url'])!='undefined' && sendObj['url']!='') {
        notificationclick_url=sendObj['url'];
    }

    return self.registration.showNotification(title, params);
}
