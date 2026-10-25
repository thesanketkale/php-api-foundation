# Operational Guide: AWS ECS Fargate Deployment via GitHub OIDC

This guide provides step-by-step instructions for deploying `php-api-foundation` to AWS Elastic Container Service (ECS) Fargate using GitHub Actions OpenID Connect (OIDC) with zero stored long-lived AWS IAM access keys.

---

## 1. Architecture Overview

```mermaid
flowchart TD
    GH["GitHub Actions Workflow (.github/workflows/deploy-aws.yml)"]
    OIDC["AWS IAM OpenID Connect Provider (token.actions.githubusercontent.com)"]
    IAM["IAM Role (GitHubActionsECSDeployRole)"]
    ECR["Amazon Elastic Container Registry (ECR)"]
    ECS["Amazon ECS Fargate Cluster"]
    ALB["Application Load Balancer"]
    RDS[("Amazon RDS MySQL 8.0")]
    SM["AWS Secrets Manager (DB password, JWT secret)"]

    GH -->|1. AssumeRoleWithWebIdentity (OIDC JWT)| OIDC
    OIDC -->|2. Issue Short-Lived STS Credentials| IAM
    GH -->|3. Docker Build & Push Image| ECR
    GH -->|4. Update ECS Service (Force New Deployment)| ECS
    ECS -->|5. Pull Container Image| ECR
    ECS -->|6. Retrieve Injected Secrets| SM
    ECS -->|7. Connect to Database| RDS
    ALB -->|8. Forward Ingress HTTPS Traffic| ECS
```

---

## 2. Prerequisites & Provisioning

Before deploying, provision the supporting AWS infrastructure. You can provision these components directly using [`terragrunt-baseline`](https://github.com/thesanketkale/terragrunt-baseline) or via the reference snippet in `deploy/aws/terragrunt-snippet.hcl`.

### Required AWS Resources:
1. **VPC & Subnets**: Public subnets for Application Load Balancer; private subnets for ECS tasks and RDS.
2. **Amazon RDS MySQL 8.0**: Multi-AZ instance in private database subnets.
3. **Amazon ECR Repository**: Named `php-api-foundation`.
4. **Amazon ECS Cluster & Fargate Service**: Configured with target group attached to ALB.
5. **AWS Secrets Manager**: Storing production database password and 256-bit `JWT_SECRET`.

---

## 3. Configuring GitHub OIDC IAM Role

### Step 1: Create IAM OIDC Provider
If your AWS account does not already have GitHub's OIDC provider configured:
- **Provider URL**: `https://token.actions.githubusercontent.com`
- **Audience**: `sts.amazonaws.com`

### Step 2: Create IAM Trust Policy
Use the trust policy in `deploy/aws/trust-policy.json`, replacing `<ACCOUNT_ID>` with your AWS Account ID:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Federated": "arn:aws:iam::<ACCOUNT_ID>:oidc-provider/token.actions.githubusercontent.com"
      },
      "Action": "sts:AssumeRoleWithWebIdentity",
      "Condition": {
        "StringEquals": {
          "token.actions.githubusercontent.com:aud": "sts.amazonaws.com"
        },
        "StringLike": {
          "token.actions.githubusercontent.com:sub": "repo:thesanketkale/php-api-foundation:*"
        }
      }
    }
  ]
}
```

### Step 3: Attach Permissions Policy
Attach `deploy/aws/ecs-service-policy.json` to allow the GitHub Actions runner to push images to ECR and update the ECS service.

---

## 4. GitHub Actions Secrets Configuration

Configure the following secrets in GitHub Repository Settings -> Secrets and variables -> Actions:

| Secret Name | Example Value | Description |
|:---|:---|:---|
| `AWS_ROLE_ARN` | `arn:aws:iam::123456789012:role/gh-actions-ecs-deploy` | IAM Role assumed via OIDC |
| `AWS_ECR_REPOSITORY` | `php-api-foundation` | Name of the ECR repository |
| `AWS_ECS_CLUSTER` | `php-api-cluster` | Name of the ECS cluster |
| `AWS_ECS_SERVICE` | `php-api-service` | Name of the ECS Fargate service |
| `APP_BASE_URL` | `https://api.example.com` | Public base URL for post-deploy smoke tests |

---

## 5. Verification Runbook

To verify the deployment pipeline:
1. Navigate to GitHub Actions -> **Deploy to AWS (ECS Fargate via OIDC)**.
2. Click **Run workflow**, selecting target environment `production` and AWS region `us-east-1`.
3. Inspect workflow execution logs:
   - Verify `aws-actions/configure-aws-credentials` authenticates without errors.
   - Verify Docker multi-stage build outputs `php-api-foundation:latest`.
   - Verify `aws ecs wait services-stable` exits with status 0.
   - Verify curl smoke test to `/v1/health/ready` returns HTTP 200.
