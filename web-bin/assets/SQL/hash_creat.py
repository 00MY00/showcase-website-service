import bcrypt

password = input("PassPhrase : ").encode('utf-8')
hashed = bcrypt.hashpw(password, bcrypt.gensalt())
print(hashed.decode())

input()
