window.OneSignal = window.OneSignal || [];
OneSignal.push(function() {
  OneSignal.init({
    appId: "99d5a086-d6b8-4542-b7f4-086163225822",
    notifyButton: {
      enable: true,
    },
  serviceWorkerParam: { scope: "/push" }, // ajusta o escopo
  });
});

OneSignal.push(function() {
OneSignal.on('subscriptionChange', function (isSubscribed) {
  if (isSubscribed) {
    OneSignal.getUserId().then(function(token) {
      console.log("Player ID:", token);
      // Envie esse ID para o seu backend e salve junto ao usuário

  fetch('salvar_token.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ token })
    });
    });
  }
});
});
OneSignal.push(function() {
OneSignal.getUserId(function(token) {
  fetch('salvar_token.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ token })
    });
});
});
