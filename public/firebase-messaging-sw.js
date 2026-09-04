// File ini WAJIB bernama persis "firebase-messaging-sw.js" dan
// WAJIB ada di root folder public/ (bukan di dalam subfolder).

importScripts("https://www.gstatic.com/firebasejs/10.13.0/firebase-app-compat.js");
importScripts("https://www.gstatic.com/firebasejs/10.13.0/firebase-messaging-compat.js");

// Config sama persis dengan yang di fcm_token_generator.html
firebase.initializeApp({
    apiKey: "AIzaSyB_eM_GuB_QmucDQ1xLh-jvdQfzfM2nL3c",
    authDomain: "absen-pintar-90392.firebaseapp.com",
    projectId: "absen-pintar-90392",
    storageBucket: "absen-pintar-90392.firebasestorage.app",
    messagingSenderId: "705718237989",
    appId: "1:705718237989:web:32e102df1d6557014f38cc"
});

const messaging = firebase.messaging();

// Ini yang nanganin notif kalau tab browser lagi DITUTUP/di-minimize
// (background). Kalau tab lagi kebuka, itu ditangani onMessage() di
// fcm_token_generator.html, bukan di sini.
messaging.onBackgroundMessage((payload) => {
    console.log('Notif diterima di background:', payload);

    self.registration.showNotification(payload.notification.title, {
        body: payload.notification.body,
        icon: '/favicon.ico',
    });
});
