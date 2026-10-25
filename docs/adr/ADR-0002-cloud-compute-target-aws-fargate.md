# ADR-0002: Cloud Compute Target Selection for AWS (ECS Fargate vs. App Runner)

## Status
Accepted

## Date
2026-10-25

## Context
Deploying `php-api-foundation` to Amazon Web Services (AWS) requires choosing a managed container compute abstraction. The two primary candidates considered are:
1. **AWS App Runner**: A fully managed container-as-a-service (CaaS) platform abstracting infrastructure, scaling, and load balancing.
2. **Amazon Elastic Container Service (ECS) on AWS Fargate**: A serverless container compute engine offering full Amazon Virtual Private Cloud (VPC) networking, fine-grained AWS Identity and Access Management (IAM) role segregation, and Application Load Balancer (ALB) integration.

The foundational API architecture requires:
- Secure, private database connectivity to Amazon RDS (Aurora MySQL / RDS MySQL) without exposing database ports to the public internet.
- Strict IAM least-privilege using GitHub Actions OpenID Connect (OIDC) for deployment and per-task IAM roles for runtime credentials (AWS Secrets Manager, KMS, CloudWatch).
- Predictable custom domain SSL termination, TLS 1.3 enforcement, and path-based routing capabilities.

## Decision
We select **Amazon ECS with AWS Fargate** as the primary AWS container compute target for `php-api-foundation`.

Key decision drivers:
1. **Native VPC Peering & Private RDS Security**:
   - ECS Fargate tasks run directly inside customer-managed private subnets within a VPC. Database traffic to Amazon RDS remains completely private, governed by security groups and network access control lists (NACLs).
   - In contrast, App Runner's VPC connector introduces egress-only networking, higher connection latency, and constraints when connecting to private subnets across multiple availability zones.
2. **Granular IAM Security via Task Roles & Task Execution Roles**:
   - ECS Fargate cleanly separates the **Task Execution Role** (used by ECS agent to pull images from private Amazon ECR and retrieve secrets from AWS Secrets Manager) from the **Task Role** (assumed by the PHP application container to interact with AWS APIs).
   - This prevents runtime application code from accessing infrastructure-level image repositories.
3. **Enterprise Ingress Control**:
   - ECS Fargate integrates directly behind an Application Load Balancer (ALB) or AWS Network Load Balancer (NLB), enabling AWS WAF (Web Application Firewall) rate limiting, AWS Shield DDoS protection, and native ACM certificate termination.
4. **GitHub Actions Deployment Integration via OIDC**:
   - The deployment pipeline leverages `aws-actions/amazon-ecs-deploy-task-definition` with short-lived tokens acquired via GitHub OIDC without storing static AWS access keys.

## Consequences

### Positive
- **Hardened Security Perimeter**: Zero public database exposure; all communication with RDS and internal AWS services occurs via private VPC endpoints and security groups.
- **Auditable Least-Privilege IAM**: Deployment roles and runtime task roles are strictly scoped and short-lived.
- **Production-Ready Scalability**: Fine-grained horizontal auto-scaling based on CPU, memory, or request counts via Target Tracking Policies on the ALB.
- **Standardized Container Topology**: The multi-stage Nginx + PHP-FPM container image runs identically across ECS Fargate, local Docker Compose, and testing harnesses.

### Negative
- **Infrastructure Overhead**: ECS Fargate requires configuring an ALB, Target Groups, VPC subnets, and Route 53 records compared to App Runner's single-click endpoint generation.
- **Baseline Cost**: The Application Load Balancer introduces a fixed baseline cost (~$16–$20/month) regardless of incoming traffic, whereas App Runner bills predominantly on compute usage.

### Neutral
- Infrastructure as Code (IaC) templates (Terraform / Terragrunt or CloudFormation) are provided in companion baseline repositories (`terragrunt-baseline`) to automate ECS Fargate cluster and task definition provisioning.
