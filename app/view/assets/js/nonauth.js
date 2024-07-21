
/**
 * Functions for Register Page (mostly just simple AJAX calls)
 */

const doCheck = (opts) => {
    const fd = new FormData();
    fd.set(opts.key, encodeURIComponent(opts.value));
    fetch(opts.location, {
        method: "post",
        body: fd
    }).then(r => r.json()).then(response => {
        document.getElementById(opts.responseElem).innerHTML = response;
    }).catch(err => console.error(err));
}
const CheckPasswords = (password) => {
    doCheck({
        location: "check.php",
        key: "password",
        value: password,
        responseElem: "passwordresult"
    });
}

const CheckUsername = (name) => {
    doCheck({
        location: "checkun.php",
        key: "username",
        value: name,
        responseElem: "usernameresult"
    });
}

function CheckEmail(email) {
    doCheck({
        location: "checkem.php",
        key: "email",
        value: email,
        responseElem: "emailresult"
    });
}

const PasswordMatch = () => {
    const pwt1 = document.getElementById("pw1").value;
    const pwt2 = document.getElementById("pw2").value;
    const resultElem = document.getElementById("cpasswordresult");
    resultElem.innerHTML = (pwt1.length > 0 && pwt1 === pwt2) ? `<span style="color: #008800;">OK</span>` : `<span style="color: #FF0000;">Not Matching</span>`;
}

const getCookieVal = (offset) => {
    let endstr = document.cookie.indexOf(";", offset);
    if (endstr === -1) {
        endstr = document.cookie.length;
    }
    return decodeURIComponent(document.cookie.substring(offset, endstr));
}

const GetCookie = (name) => {
    const arg = name + "=";
    const alen = arg.length;
    const clen = document.cookie.length;
    let i = 0;
    while (i < clen) {
        const j = i + alen;
        if (document.cookie.substring(i, j) === arg) {
            return getCookieVal(j);
        }
        i = document.cookie.indexOf(" ", i) + 1;
        if (i === 0) {
            break;
        }
    }
    return null;
}

const SetCookie = (name, value, expires, path = null, domain = null, secure = null) => {
    document.cookie = name + "=" + encodeURIComponent(value) + ((expires) ? "; expires=" + expires.toDateString() : "") + ((path) ? "; path=" + path : "") + ((domain) ? "; domain=" + domain : "") + ((secure) ? "; secure" : "");
}

const DeleteCookie = (name, path, domain) => {
    if (GetCookie(name)) {
        document.cookie = name + "=" + ((path) ? "; path=" + path : "") + ((domain) ? "; domain=" + domain : "") + "; expires=Thu, 01-Jan-70 00:00:01 GMT";
    }
}

let usr;
let pw;
let sv;

const getme = () => {
    usr = document.login.username;
    pw = document.login.password;
    sv = document.login.save;

    if (GetCookie('username') != null) {
        usr.value = GetCookie('username');
        pw.value = GetCookie('password');
    }
    if (GetCookie('save') === 'true') {
        sv[0].checked = true;
    } else {
        sv[1].checked = true;
    }

}

const saveme = () => {
    if (usr.value.length > 0 && pw.value.length > 0) {
        if (sv[0].checked) {
            let expdate = new Date();
            expdate.setTime(expdate.getTime() + 31536000000);
            SetCookie('username', usr.value, expdate);
            SetCookie('password', pw.value, expdate);
            SetCookie('save', 'true', expdate);
        }
        if (sv[1].checked) {
            DeleteCookie('username');
            DeleteCookie('password');
            DeleteCookie('save');
        }
    } else {
        alert('You must enter a username/password.');
        return false;
    }
}
