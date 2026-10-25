# deploy/aws/terragrunt-snippet.hcl
# Reference configuration for provisioning php-api-foundation ECS Fargate service
# using terragrunt-baseline modules (https://github.com/thesanketkale/terragrunt-baseline).

terraform {
  source = "tfr:///terraform-aws-modules/ecs/aws//modules/service?version=5.11.4"
}

include "root" {
  path = find_in_parent_folders()
}

inputs = {
  name        = "php-api-foundation"
  cluster_arn = dependency.ecs_cluster.outputs.cluster_arn

  cpu    = 256
  memory = 512

  # Enable AWS Fargate launch type
  launch_type = "FARGATE"

  container_definitions = {
    php-api = {
      image = "${dependency.ecr.outputs.repository_url}:latest"
      essential = true

      port_mappings = [
        {
          name          = "http"
          containerPort = 80
          hostPort      = 80
          protocol      = "tcp"
        }
      ]

      environment = [
        { name = "APP_ENV", value = "production" },
        { name = "APP_DEBUG", value = "false" },
        { name = "DB_CONNECTION", value = "mysql" },
        { name = "DB_HOST", value = dependency.rds.outputs.db_instance_address },
        { name = "DB_PORT", value = "3306" },
        { name = "DB_DATABASE", value = "php_api_prod" }
      ]

      secrets = [
        {
          name      = "DB_PASSWORD"
          valueFrom = "${dependency.secrets.outputs.db_secret_arn}:password::"
        },
        {
          name      = "JWT_SECRET"
          valueFrom = "${dependency.secrets.outputs.jwt_secret_arn}:secret::"
        }
      ]

      readonly_root_filesystem = false

      log_configuration = {
        logDriver = "awslogs"
        options = {
          "awslogs-group"         = "/ecs/php-api-foundation"
          "awslogs-region"        = "us-east-1"
          "awslogs-stream-prefix" = "ecs"
        }
      }
    }
  }

  load_balancer = {
    service = {
      target_group_arn = dependency.alb.outputs.target_group_arns[0]
      container_name   = "php-api"
      container_port   = 80
    }
  }

  subnet_ids = dependency.vpc.outputs.private_subnets
  security_group_rules = {
    alb_ingress = {
      type                     = "ingress"
      from_port                = 80
      to_port                  = 80
      protocol                 = "tcp"
      source_security_group_id = dependency.alb.outputs.security_group_id
    }
    egress_all = {
      type        = "egress"
      from_port   = 0
      to_port     = 0
      protocol    = "-1"
      cidr_blocks = ["0.0.0.0/0"]
    }
  }
}
