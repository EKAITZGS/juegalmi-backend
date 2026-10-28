output "bastion_ip_publica" {
  description = "IP pública del bastión (cambia cada vez que se para y se arranca el lab)"
  value       = aws_instance.bastion.public_ip
}

output "ip_privada_symfony" {
  value = aws_instance.symfony.private_ip
}

output "ssh_bastion" {
  value = "ssh -i ~/.ssh/vockey.pem ubuntu@${aws_instance.bastion.public_ip}"
}

output "ssh_symfony" {
  description = "Salto por el bastión con ProxyCommand: -J no aplica la opción -i al primer salto"
  value       = "ssh -i ~/.ssh/vockey.pem -o \"ProxyCommand=ssh -i ~/.ssh/vockey.pem -W %h:%p ubuntu@${aws_instance.bastion.public_ip}\" ubuntu@${aws_instance.symfony.private_ip}"
}
