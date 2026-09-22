from fastapi import FastAPI
import httpx

app = FastAPI(title="Local API Gateway")

BACKEND_URL = "http://192.168.100.222:9000"

@app.get("/api/products")
async def products():
    async with httpx.AsyncClient() as client:
        response = await client.get(
            f"{BACKEND_URL}/products"
        )
    return response.json()

@app.get("/api/orders")
async def orders():
    async with httpx.AsyncClient() as client:
        response = await client.get(
            f"{BACKEND_URL}/orders"
        )
    return response.json()