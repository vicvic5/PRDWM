from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import secrets
from datetime import datetime, timedelta

app = FastAPI(title="Auth Service")

# 1. Usuarios simulados
# En un sistema real no se almacenan contraseñas en texto plano, se usaría Argon2.
USERS = {
    "ana": {
        "user_id": "USR-001",
        "password_hash": "1234", 
        "roles": ["user"]
    },
    "ernesto": {
        "user_id": "USR-003",
        "password_hash": "admin123", 
        "roles": ["user", "admin"]
    }
}

# 2. Sesiones en memoria
SESSIONS = {}

# Modelos de datos para las peticiones
class LoginRequest(BaseModel):
    username: str
    password: str

class TokenRequest(BaseModel):
    token: str

@app.post("/login")
def login(req: LoginRequest):
    # Verificar credenciales
    user = USERS.get(req.username)
    if not user or user["password_hash"] != req.password:
        # Si el login es incorrecto, debe devolver 401 Unauthorized
        raise HTTPException(status_code=401, detail="Unauthorized") 

    # Generar token opaco y asociar sesión
    token = secrets.token_hex(32)
    SESSIONS[token] = {
        "user_id": user["user_id"],
        "username": req.username,
        "roles": user["roles"],
        "expires_at": datetime.utcnow() + timedelta(seconds=900)
    }

    # Respuesta esperada con el token generado
    return {
        "access_token": token,
        "token_type": "bearer",
        "expires_in": 900
    }

@app.post("/introspect")
def introspect(req: TokenRequest):
    # Indicar si el token sigue activo y devolver identidad
    session = SESSIONS.get(req.token)
    
    if session and session["expires_at"] > datetime.utcnow():
        return {
            "active": True,
            "user_id": session["user_id"],
            "username": session["username"],
            "roles": session["roles"]
        }
        
    # Token expirado o inexistente
    return {"active": False}

@app.post("/logout")
def logout(req: TokenRequest):
    # Revocar la sesión eliminándola del diccionario
    if req.token in SESSIONS:
        del SESSIONS[req.token]
    return {"message": "Sesión revocada exitosamente"}