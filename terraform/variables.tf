variable "region" {
  description = "Región de AWS (el Learner Lab trabaja en us-east-1)"
  type        = string
  default     = "us-east-1"
}

variable "zona" {
  description = "Zona de disponibilidad de las dos subredes"
  type        = string
  default     = "us-east-1a"
}

variable "ami" {
  description = "Ubuntu Server 26.04 LTS (amd64), la misma imagen que se vio en la consola"
  type        = string
  default     = "ami-0d97d7f07b25dd693"
}

variable "clave" {
  description = "Par de claves del Learner Lab (vockey.pem)"
  type        = string
  default     = "vockey"
}

variable "mi_ip" {
  description = "IP pública desde la que se permite SSH al bastión, sin /32. Se pasa con -var"
  type        = string
}
