# Operational Guide: Azure App Service Deployment via GitHub OIDC

This guide provides instructions for deploying `php-api-foundation` as a containerized workload to Azure App Service (Linux Web App for Containers) using Microsoft Entra ID Workload Identity federation (OIDC) without long-lived client secrets.

---

## 1. Architecture Overview

```mermaid
flowchart TD
    GH["GitHub Actions Workflow (.github/workflows/deploy-azure.yml)"]
    Entra["Microsoft Entra ID (Azure AD OIDC Federation)"]
    ACR["Azure Container Registry (ACR)"]
    AppService["Azure App Service (Linux Web App for Containers)"]
    MySQL[("Azure Database for MySQL Flexible Server")]
    KeyVault["Azure Key Vault (Secrets & JWT Configuration)"]

    GH -->|1. Azure Login (Federated Identity Credential)| Entra
    GH -->|2. Docker Build & Push Image| ACR
    GH -->|3. Deploy Container to Web App| AppService
    AppService -->|4. Pull Image via Managed Identity| ACR
    AppService -->|5. Read Connection String & JWT Secret| KeyVault
    AppService -->|6. Connect via SSL/TLS| MySQL
```

---

## 2. Automated Provisioning with Azure CLI

Use the provided automation script `deploy/azure/azure-oidc-setup.sh` to configure all necessary Azure resources:

```bash
# 1. Log in to Azure CLI
az login

# 2. Set environment variables
export AZURE_RESOURCE_GROUP="rg-php-api-foundation"
export AZURE_LOCATION="eastus"
export AZURE_APP_NAME="php-api-foundation-app"
export AZURE_ACR_NAME="acrphpapifoundation"
export AZURE_PLAN_NAME="plan-php-api-foundation"

# 3. Execute setup script
bash deploy/azure/azure-oidc-setup.sh
```

---

## 3. GitHub Actions Secrets Configuration

Configure the following secrets output by the script in GitHub Repository Settings:

| Secret Name | Example Value | Description |
|:---|:---|:---|
| `AZURE_CLIENT_ID` | `00000000-0000-0000-0000-000000000000` | Entra App Registration Application (Client) ID |
| `AZURE_TENANT_ID` | `11111111-1111-1111-1111-111111111111` | Microsoft Entra Directory (Tenant) ID |
| `AZURE_SUBSCRIPTION_ID`| `22222222-2222-2222-2222-222222222222` | Target Azure Subscription ID |
| `AZURE_ACR_NAME` | `acrphpapifoundation` | Name of Azure Container Registry |
| `AZURE_APP_NAME` | `php-api-foundation-app` | Name of the App Service Web App |
| `APP_BASE_URL` | `https://php-api-foundation-app.azurewebsites.net` | Public base URL for smoke tests |

---

## 4. Verification Runbook

1. Navigate to GitHub Actions -> **Deploy to Azure (App Service via OIDC)**.
2. Click **Run workflow** against `main`.
3. Check workflow execution steps:
   - `azure/login` succeeds with Federated Identity credentials.
   - `az acr login` acquires ACR authentication token.
   - Docker container pushes to `<ACR_NAME>.azurecr.io/php-api-foundation:<SHA>`.
   - `azure/webapps-deploy` triggers App Service image swap.
   - Smoke test confirms `/v1/health/ready` returns HTTP 200.
