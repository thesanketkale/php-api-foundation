# ADR-0004: Cloud Compute Target Selection for Azure (App Service Linux Container vs. Azure Container Apps)

## Status
Accepted

## Date
2026-10-25

## Context
Deploying `php-api-foundation` to Microsoft Azure requires selecting a managed container hosting platform. The two primary serverless/managed compute candidates are:
1. **Azure Container Apps (ACA)**: A serverless container platform built on Kubernetes (K8s/KEDA/Envoy) providing microservice primitives, dynamic zero-to-N scaling, and Dapr integration.
2. **Azure App Service (Linux Web App for Containers)**: A mature, enterprise platform-as-a-service (PaaS) container runtime offering predictable compute tiers, native deployment slots, built-in TLS management, and direct integration with Azure Virtual Networks (VNet).

The foundational REST API requirements on Azure include:
- Predictable performance and latency without cold-start penalties.
- Simplified automated custom domain binding with free managed TLS/SSL certificates.
- Seamless deployment slots with zero-downtime swap and immediate rollback capabilities.
- Direct connectivity to Azure Database for MySQL Flexible Server via VNet integration.
- Continuous deployment via GitHub Actions with OpenID Connect (OIDC) authentication (`azure/login` using Federated Credentials).

## Decision
We select **Azure App Service (Linux Web App for Containers)** as the primary Azure compute target for `php-api-foundation`.

Key decision drivers:
1. **Deployment Slots with Zero-Downtime Swap**:
   - Azure App Service provides native `staging` and `production` deployment slots.
   - The CI/CD pipeline deploys the new container image to the staging slot, performs automated HTTP health and smoke checks against `/v1/health/ready`, and executes a slot swap (`az webapp deployment slot swap`).
   - If smoke testing fails, traffic is never routed to the new image, providing zero downtime and instantaneous rollback.
   - While Azure Container Apps supports revisions and traffic weighting, it lacks the full PaaS slot swap semantics and automated warmup routing built into App Service.
2. **Managed Custom Domains & Automated Free TLS**:
   - App Service Managed Certificates offer free, auto-renewing SNI SSL certificates directly managed by Azure.
   - This eliminates the need for external Let's Encrypt bot containers or manual certificate rotation pipelines.
3. **VNet Integration for Secure Private Database Access**:
   - Regional VNet integration allows App Service to communicate privately with Azure Database for MySQL Flexible Server inside private endpoints or delegated subnets, without exposing database firewall rules to public IPs.
4. **Predictable Pricing & Cold Start Prevention**:
   - Basic/Standard App Service plans (e.g. `B1`, `P1v3`) run dedicated, warm compute instances, ensuring predictable sub-5ms API response times without the zero-scaling cold-start delays that impact ACA when scale-down-to-zero is enabled.
5. **Native GitHub Actions OIDC Authentication**:
   - Authentication is performed via `azure/login` using Microsoft Entra ID (Azure AD) Federated Credentials, completely eliminating stored static client secrets.

## Consequences

### Positive
- **Rock-Solid Deployment Workflows**: Staging slot warmup and atomic swap guarantee zero downtime during deployments.
- **Enterprise Network Security**: VNet integration keeps MySQL Flexible Server isolated from public ingress.
- **Operational Simplicity**: Avoids Kubernetes and Envoy complexity present in Azure Container Apps; logs stream directly to Azure Application Insights or standard Log Analytics workspaces.
- **Automated TLS**: Free App Service Managed Certificates handle TLS lifecycle automatically.

### Negative
- **Fixed Minimum Cost**: Dedicated App Service plans incur a fixed monthly compute cost (starting at ~$13–$55/month depending on SKU) even during idle periods, whereas Azure Container Apps charges per second of active execution.
- **Coarse Horizontal Scaling**: Scaling is determined by VM instance counts rather than fine-grained per-request HTTP concurrency autoscaling.

### Neutral
- A setup script (`deploy/azure/setup-appservice.sh`) and GitHub Actions workflow (`.github/workflows/deploy-azure.yml`) are provided to automate resource group, App Service plan, container web app, and deployment slot provisioning.
