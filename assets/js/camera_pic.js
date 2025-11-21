let stream;

function openCamera() {
  navigator.mediaDevices.getUserMedia({ video: true })
    .then(s => {
      stream = s;
      document.getElementById('camera').srcObject = stream;
      document.getElementById('camera').classList.remove('hidden');
      document.getElementById('snapBtn').classList.remove('hidden');
    })
    .catch(err => alert("Camera access denied: " + err));
}

function takeSnapshot() {
  let video = document.getElementById('camera');
  let canvas = document.getElementById('snapshot');
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  canvas.getContext('2d').drawImage(video, 0, 0);

  canvas.classList.remove('hidden');

  // Convert to Base64 image
  let dataURL = canvas.toDataURL('image/png');
  document.getElementById('profile_pic_data').value = dataURL;

  // Stop camera
  stream.getTracks().forEach(track => track.stop());
  document.getElementById('camera').classList.add('hidden');
  document.getElementById('snapBtn').classList.add('hidden');
}

function toggleConfirmPassword() {
  const pass = document.getElementById("confirm_password");
  pass.type = pass.type === "password" ? "text" : "password";
}
const phoneInput = document.querySelector("#phone");
const whatsappInput = document.querySelector("#whatsapp");




document.querySelector("form").addEventListener("submit", function(e) {
  const pass = document.getElementById("password").value;
  const confirm = document.getElementById("confirm_password").value;

  if (pass !== confirm) {
    e.preventDefault();
    Swal.fire({
      icon: 'error',
      title: 'Password mismatch',
      text: 'Password and Confirm Password must match.',
      confirmButtonColor: '#3085d6'
    });
  }
});

