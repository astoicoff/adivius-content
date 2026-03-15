const SUPABASE_URL = "https://ptonwhknnjtudwvwwdnn.supabase.co";
const SUPABASE_ANON_KEY = "sb_publishable_3Xfgg2eUSJaybbLZTyaGIQ_wQ4tayHL";

const { createClient } = supabase;
const sb = createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// If already logged in, go to dashboard
sb.auth.getSession().then(({ data }) => {
    if (data.session) {
        window.location.href = "/dashboard";
    }
});

const tabs = document.querySelectorAll(".auth-tab");
const loginForm = document.getElementById("loginForm");
const registerForm = document.getElementById("registerForm");
const authMessage = document.getElementById("authMessage");

tabs.forEach(tab => {
    tab.addEventListener("click", () => {
        tabs.forEach(t => t.classList.remove("active"));
        tab.classList.add("active");
        const target = tab.dataset.tab;
        loginForm.classList.toggle("active", target === "login");
        registerForm.classList.toggle("active", target === "register");
        clearMessage();
    });
});

function showMessage(text, type = "error") {
    authMessage.textContent = text;
    authMessage.className = `auth-message ${type}`;
}

function clearMessage() {
    authMessage.className = "auth-message";
    authMessage.textContent = "";
}

loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const email = document.getElementById("loginEmail").value.trim();
    const password = document.getElementById("loginPassword").value;
    const btn = document.getElementById("loginBtn");

    btn.disabled = true;
    btn.textContent = "Signing in...";
    clearMessage();

    const { error } = await sb.auth.signInWithPassword({ email, password });

    if (error) {
        showMessage(error.message);
        btn.disabled = false;
        btn.textContent = "Sign In";
    } else {
        window.location.href = "/dashboard";
    }
});

registerForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const email = document.getElementById("registerEmail").value.trim();
    const password = document.getElementById("registerPassword").value;
    const confirm = document.getElementById("registerConfirm").value;
    const btn = document.getElementById("registerBtn");

    if (password !== confirm) {
        showMessage("Passwords do not match.");
        return;
    }

    btn.disabled = true;
    btn.textContent = "Creating account...";
    clearMessage();

    const { error } = await sb.auth.signUp({ email, password });

    if (error) {
        showMessage(error.message);
        btn.disabled = false;
        btn.textContent = "Create Account";
    } else {
        showMessage("Account created! Check your email to confirm, then sign in.", "success");
        btn.disabled = false;
        btn.textContent = "Create Account";
    }
});
