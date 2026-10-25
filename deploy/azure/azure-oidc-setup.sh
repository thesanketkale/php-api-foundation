#!/usr/bin/env bash
# deploy/azure/azure-oidc-setup.sh: Provision Azure OIDC federated identity credential
# and infrastructure for GitHub Actions deployment to Azure App Service.
# Run locally by repository owner: bash deploy/azure/azure-oidc-setup.sh

set -euo pipefail

echo "==> Configuring Azure OIDC and App Service resources"

# Configurable variables
RESOURCE_GROUP="${AZURE_RESOURCE_GROUP:-rg-php-api-foundation}"
LOCATION="${AZURE_LOCATION:-eastus}"
APP_NAME="${AZURE_APP_NAME:-php-api-foundation-app}"
ACR_NAME="${AZURE_ACR_NAME:-acrphpapifoundation}"
APP_SERVICE_PLAN="${AZURE_PLAN_NAME:-plan-php-api-foundation}"
GITHUB_REPO="thesanketkale/php-api-foundation"

# 1. Create Resource Group
echo "==> Creating Resource Group: ${RESOURCE_GROUP}..."
az group create --name "${RESOURCE_GROUP}" --location "${LOCATION}"

# 2. Create Azure Container Registry (ACR)
echo "==> Creating Azure Container Registry: ${ACR_NAME}..."
az acr create --resource-group "${RESOURCE_GROUP}" --name "${ACR_NAME}" --sku Basic --admin-enabled false

# 3. Create App Service Plan (Linux)
echo "==> Creating App Service Plan (Linux B1)..."
az appservice plan create \
  --name "${APP_SERVICE_PLAN}" \
  --resource-group "${RESOURCE_GROUP}" \
  --is-linux \
  --sku B1

# 4. Create Web App for Containers
echo "==> Creating Web App for Containers..."
az webapp create \
  --resource-group "${RESOURCE_GROUP}" \
  --plan "${APP_SERVICE_PLAN}" \
  --name "${APP_NAME}" \
  --deployment-container-image-name "mcr.microsoft.com/appsvc/staticsite:latest"

# 5. Create Microsoft Entra ID Application for GitHub Actions OIDC
echo "==> Creating Microsoft Entra ID Application..."
APP_ID=$(az ad app create --display-name "gh-actions-php-api-foundation" --query appId -o tsv)
SP_ID=$(az ad sp create --id "${APP_ID}" --query id -o tsv)

SUBSCRIPTION_ID=$(az account show --query id -o tsv)
TENANT_ID=$(az account show --query tenantId -o tsv)

# Assign Contributor role to the resource group
az role assignment create \
  --role "Contributor" \
  --assignee-object-id "${SP_ID}" \
  --assignee-principal-type "ServicePrincipal" \
  --scope "/subscriptions/${SUBSCRIPTION_ID}/resourceGroups/${RESOURCE_GROUP}"

# Assign AcrPush role to ACR
ACR_ID=$(az acr show --name "${ACR_NAME}" --query id -o tsv)
az role assignment create \
  --role "AcrPush" \
  --assignee-object-id "${SP_ID}" \
  --assignee-principal-type "ServicePrincipal" \
  --scope "${ACR_ID}"

# 6. Create Federated Identity Credential for GitHub repository
echo "==> Creating Federated Identity Credential for ${GITHUB_REPO}..."
cat <<EOF > /tmp/azure_federated_credential.json
{
  "name": "gh-actions-php-api-main",
  "issuer": "https://token.actions.githubusercontent.com",
  "subject": "repo:${GITHUB_REPO}:ref:refs/heads/main",
  "description": "GitHub Actions main branch deployment",
  "audiences": ["api://AzureADTokenExchange"]
}
EOF

az ad app federated-credential create --id "${APP_ID}" --parameters /tmp/azure_federated_credential.json
rm -f /tmp/azure_federated_credential.json

echo "==> Azure Setup Complete!"
echo "Configure the following GitHub Actions secrets in your repository settings:"
echo "  AZURE_CLIENT_ID       : ${APP_ID}"
echo "  AZURE_TENANT_ID       : ${TENANT_ID}"
echo "  AZURE_SUBSCRIPTION_ID : ${SUBSCRIPTION_ID}"
echo "  AZURE_ACR_NAME        : ${ACR_NAME}"
echo "  AZURE_APP_NAME        : ${APP_NAME}"
