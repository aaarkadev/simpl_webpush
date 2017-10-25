
function webpush() {
    var self=this;

    this.is_debug=false;
    this.log_str='';

    this.set_debug = function() {
        self.is_debug=true;
    }

    this.init_webpush = function() {
        try {

            if (!('serviceWorker' in navigator)) {
                self.log("Service workers are not supported by this browser");
                return false;
            }

            if (!('PushManager' in window)) {
                self.log('Push notifications are not supported by this browser');
                return false;
            }

            if (!('showNotification' in ServiceWorkerRegistration.prototype)) {
                self.log('Notifications are not supported by this browser');
                return false;
            }

            if (!('Notification' in window)) {
                self.log('Notification are not supported by this browser');
                return false;
            }

            if (Notification.permission === 'denied') {
                self.log('Notifications are denied by the user');
                return false;
            }

            if(typeof(window['webpushServerKey'])=='undefined') {
                window['webpushServerKey']= "BJs/+1111111111111111111111111111111111111111111111111111111111111111111111111111111111=";
            }


            if (navigator.serviceWorker.controller) {
                //var serviceWorker_url = navigator.serviceWorker.controller.scriptURL;
                self.log('serviceWorker already registered');
            } else {
                navigator.serviceWorker.register('/serviceWorker.js')
                    .then(function(registration) {
                        self.log('new serviceWorker registered');
                        self.setCookie('webpush_serviceworker_time',(''+parseInt(Date.now()/1000)),2);
                    }).catch(function(err) {
                    self.log(':^('+ err);
                });
            }

            navigator.serviceWorker.ready.then(function(registration) {
                self.subscribe(registration);
 

            });

            navigator.serviceWorker.addEventListener('controllerchange', function() {
                self.log('serviceWorker changed');
            });

        } catch (err) { }
    };

    this.subscribe = function(registration) {
        registration.pushManager.getSubscription()
            .then(function(subscription) {

                if (subscription) {
                    return subscription;
                }

                return registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: self.urlBase64ToUint8Array(window['webpushServerKey'])
                });
            }).then(function(subscription) {

            if(!subscription)
                return false;

            self.log('subscription.endpoint:'+ subscription.endpoint+' hash:'+self.hashCode(subscription.endpoint)+' cookhash:'+self.getCookie('webpush_endpoint'));

            if(self.getCookie('webpush_endpoint')==self.hashCode(subscription.endpoint)) {
                return false;
            }

            self.setCookie('webpush_endpoint',self.hashCode(subscription.endpoint),30);

            var key_val='';
            var token_val='';
            if(typeof(subscription.getKey)!='undefined') {
                key_val= btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh'))));
                token_val=btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth'))));
            }

            fetch('/ajax_webpush.php?registr_endpoint=1', {
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
            self.log(':^('+ err);
        });
    };

    this.urlBase64ToUint8Array = function(base64String) {
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
    };

    this.setCookie = function(name,val,save_days) {
        document.cookie = (name+"=" + escape(val) + "; ");
    };


    this.getCookie = function(name){
        var matches = document.cookie.match(new RegExp(
            "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
        ));
        return matches ? decodeURIComponent(matches[1]) : '';
    }

    this.hashCode = function(str) {
        var hash = 0, i, chr;
        if (str.length === 0) return hash;
        for (i = 0; i < str.length; i++) {
            chr   = str.charCodeAt(i);
            hash  = ((hash << 5) - hash) + chr;
            hash |= 0;
        }
        return hash;
    };


    this.log = function(str) {
        if(self.is_debug) {

            console.log(str);
            self.log_str=(str+' '+this.log_str);

            setTimeout(function() {
                if(document.getElementById('pageBody'))
                    document.getElementById('pageBody').textContent = self.log_str;
            },3000);
        }
    };

}


var wObj=new webpush();
wObj.init_webpush();
