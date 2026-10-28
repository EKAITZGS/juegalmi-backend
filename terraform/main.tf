# Jokoa by JuegAlmi: red con un bastión público y una máquina privada (Symfony: API y web Angular).
#
#   Internet --> [bastión: IP pública, SSH + proxy 80/443] --> [symfony: 10.0.2.10]
##
# Las máquinas privadas no tienen IP pública. Salen a Internet (apt, Docker, composer) a través
# del bastión, que hace de NAT, y solo aceptan SSH y HTTP desde el bastión.

terraform {
  required_version = ">= 1.5"
  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = ">= 5.0"
    }
  }
}

provider "aws" {
  region = var.region
}

# ---------- Red ----------

resource "aws_vpc" "jokoa" {
  cidr_block           = "10.0.0.0/16"
  enable_dns_hostnames = true
  tags                 = { Name = "jokoa-vpc" }
}

resource "aws_internet_gateway" "jokoa" {
  vpc_id = aws_vpc.jokoa.id
  tags   = { Name = "jokoa-igw" }
}

resource "aws_subnet" "publica" {
  vpc_id                  = aws_vpc.jokoa.id
  cidr_block              = "10.0.1.0/24"
  availability_zone       = var.zona
  map_public_ip_on_launch = true
  tags                    = { Name = "jokoa-publica" }
}

resource "aws_subnet" "privada" {
  vpc_id            = aws_vpc.jokoa.id
  cidr_block        = "10.0.2.0/24"
  availability_zone = var.zona
  tags              = { Name = "jokoa-privada" }
}

resource "aws_route_table" "publica" {
  vpc_id = aws_vpc.jokoa.id
  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.jokoa.id
  }
  tags = { Name = "jokoa-rt-publica" }
}

resource "aws_route_table_association" "publica" {
  subnet_id      = aws_subnet.publica.id
  route_table_id = aws_route_table.publica.id
}

# La salida de la subred privada pasa por el bastión (NAT): un NAT Gateway cuesta ~1 $/día
resource "aws_route_table" "privada" {
  vpc_id = aws_vpc.jokoa.id
  route {
    cidr_block           = "0.0.0.0/0"
    network_interface_id = aws_instance.bastion.primary_network_interface_id
  }
  tags = { Name = "jokoa-rt-privada" }
}

resource "aws_route_table_association" "privada" {
  subnet_id      = aws_subnet.privada.id
  route_table_id = aws_route_table.privada.id
}

# ---------- Grupos de seguridad ----------

resource "aws_security_group" "bastion" {
  name        = "jokoa-bastion"
  description = "Bastion: SSH solo desde mi IP, web abierta y salida NAT de la subred privada"
  vpc_id      = aws_vpc.jokoa.id

  ingress {
    description = "SSH desde mi IP"
    from_port   = 22
    to_port     = 22
    protocol    = "tcp"
    cidr_blocks = ["${var.mi_ip}/32"]
  }
  ingress {
    description = "HTTP (validacion del certificado y redireccion)"
    from_port   = 80
    to_port     = 80
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }
  ingress {
    description = "HTTPS"
    from_port   = 443
    to_port     = 443
    protocol    = "tcp"
    cidr_blocks = ["0.0.0.0/0"]
  }
  ingress {
    description = "Trafico de las maquinas privadas que sale a Internet por el bastion (NAT)"
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = [aws_subnet.privada.cidr_block]
  }
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
  tags = { Name = "jokoa-sg-bastion" }
}

resource "aws_security_group" "privada" {
  name        = "jokoa-privada"
  description = "Maquinas privadas: SSH y HTTP solo desde el bastion"
  vpc_id      = aws_vpc.jokoa.id

  ingress {
    description     = "SSH desde el bastion"
    from_port       = 22
    to_port         = 22
    protocol        = "tcp"
    security_groups = [aws_security_group.bastion.id]
  }
  ingress {
    description     = "HTTP desde el bastion (proxy inverso)"
    from_port       = 80
    to_port         = 80
    protocol        = "tcp"
    security_groups = [aws_security_group.bastion.id]
  }
  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
  tags = { Name = "jokoa-sg-privada" }
}

# ---------- Máquinas ----------

resource "aws_instance" "bastion" {
  ami                    = var.ami
  instance_type          = "t3.micro"
  key_name               = var.clave
  subnet_id              = aws_subnet.publica.id
  vpc_security_group_ids = [aws_security_group.bastion.id]
  source_dest_check      = false # imprescindible para que pueda hacer de NAT
  user_data              = file("${path.module}/bastion-nat.sh")

  root_block_device {
    volume_size = 10
    volume_type = "gp3"
  }
  tags = { Name = "jokoa-bastion" }
}

resource "aws_instance" "symfony" {
  ami                         = var.ami
  instance_type               = "t3.small"
  key_name                    = var.clave
  subnet_id                   = aws_subnet.privada.id
  private_ip                  = "10.0.2.10"
  associate_public_ip_address = false
  vpc_security_group_ids      = [aws_security_group.privada.id]

  root_block_device {
    volume_size = 20
    volume_type = "gp3"
  }
  tags       = { Name = "jokoa-symfony" }
  depends_on = [aws_route_table_association.privada]
}
